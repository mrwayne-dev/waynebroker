<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Domains\Wallet\LedgerService;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Money\Cents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The concurrent half of ledger idempotency — Maveren C-3's redelivery window.
 *
 * Not RefreshDatabase, and for the same reason as ConcurrentWithdrawalRaceTest:
 * the scenario is two IPN deliveries of the same payment arriving before either
 * has committed, which can only be set up with real committed state on two
 * connections. The deterministic construction is the same REPEATABLE READ
 * snapshot trick: the losing delivery takes its read snapshot before the
 * winning delivery commits, so its fast-path lookup misses the key and it
 * proceeds to an INSERT that the UNIQUE index then rejects — exercising the
 * service's catch path, which must return the winner's entry rather than
 * bubbling a duplicate-key error to the caller.
 *
 * The sequential idempotency case (same key, one after another) is a plain
 * RefreshDatabase test in LedgerServiceTest.
 */
final class LedgerIdempotencyConcurrencyTest extends TestCase
{
    private const RACER = 'ledger_ipn_racer';

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ledger_entries')) {
            Artisan::call('migrate', ['--force' => true]);
        }

        $this->resetTables();

        config()->set('database.connections.'.self::RACER, config('database.connections.mysql'));
        DB::purge(self::RACER);

        foreach (['mysql', self::RACER] as $connection) {
            DB::connection($connection)->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection($connection)->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        $user = User::factory()->create();
        $this->wallet = Wallet::query()->where('user_id', $user->id)->sole();
    }

    protected function tearDown(): void
    {
        if (DB::connection(self::RACER)->transactionLevel() > 0) {
            DB::connection(self::RACER)->rollBack();
        }

        DB::purge(self::RACER);
        $this->resetTables();

        parent::tearDown();
    }

    public function test_a_redelivered_credit_with_the_same_key_is_applied_once(): void
    {
        $key = 'ipn-'.uniqid();

        // (1) The losing delivery opens its transaction and fixes its snapshot
        //     while no entry with this key exists yet.
        DB::connection(self::RACER)->beginTransaction();
        $seenBefore = LedgerEntry::on(self::RACER)->where('idempotency_key', $key)->exists();
        $this->assertFalse($seenBefore, 'The losing delivery must start before the key exists.');

        // (2) The winning delivery credits on the default connection and commits.
        $winner = app(LedgerService::class)->credit($this->wallet, Cents::from(400), 'deposit', idempotencyKey: $key);

        // (3) The losing delivery credits inside its stale snapshot. Its
        //     fast-path lookup misses (snapshot), its INSERT collides with the
        //     winner on the UNIQUE index, and the service must hand back the
        //     winner's entry — no duplicate, no exception.
        $loser = $this->creditOnRacer($key);

        DB::connection(self::RACER)->commit();

        $this->assertSame($winner->id, $loser->id, 'The losing delivery must return the winner\'s entry.');

        $this->assertSame(
            1,
            LedgerEntry::query()->where('wallet_id', $this->wallet->id)->where('idempotency_key', $key)->count(),
            'The key must appear exactly once in the ledger.',
        );

        $this->assertSame(
            400,
            (int) DB::table('wallets')->where('id', $this->wallet->id)->value('balance_cents'),
            'The wallet must be credited once, not twice.',
        );
    }

    private function creditOnRacer(string $key): LedgerEntry
    {
        $previousDefault = DB::getDefaultConnection();
        DB::setDefaultConnection(self::RACER);

        try {
            return app(LedgerService::class)->credit($this->wallet, Cents::from(400), 'deposit', idempotencyKey: $key);
        } finally {
            DB::setDefaultConnection($previousDefault);
        }
    }

    private function resetTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('ledger_entries')->truncate();
        DB::table('wallets')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
