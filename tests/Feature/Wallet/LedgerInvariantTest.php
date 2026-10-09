<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Domains\Wallet\Exceptions\InsufficientFundsException;
use App\Domains\Wallet\LedgerService;
use App\Domains\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Money\Cents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The ledger invariant holds across long randomised sequences of operations.
 *
 * Two properties, checked after every single operation:
 *   1. SUM(credit amount_cents) - SUM(debit amount_cents) == wallets.balance_cents
 *      — the balance is exactly the ledger, never drifting from it (R6, gross).
 *   2. The wallet is never overdrawn: balance_cents >= 0 and
 *      balance_cents - reserved_cents >= 0 at all times, so no debit ever
 *      succeeded that should not have.
 *
 * Not RefreshDatabase: the sequence mixes sequential operations with genuine
 * two-connection parallel debit pairs (the C-1 shape, made deterministic by the
 * same REPEATABLE READ snapshot interleave as ConcurrentWithdrawalRaceTest),
 * and a parallel pair needs committed state on two connections. If the
 * invariant survives a hundred of these, the service is not merely correct on
 * the paths a hand-written test happened to pick.
 */
final class LedgerInvariantTest extends TestCase
{
    private const OPERATIONS = 100;

    private const RACER = 'ledger_invariant_racer';

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

    public function test_the_balance_equals_the_ledger_through_a_mixed_sequence(): void
    {
        $ledger = app(LedgerService::class);

        // Seed with an opening balance so debits have something to draw on.
        $ledger->credit($this->wallet, Cents::from(10_000), 'deposit');
        $this->assertInvariant();

        for ($i = 0; $i < self::OPERATIONS; $i++) {
            $spendable = $this->spendable();

            match (random_int(0, 3)) {
                // Credit: always valid.
                0 => $ledger->credit($this->wallet, Cents::from(random_int(1, 5_000)), 'roi_payout'),

                // Sequential debit that may or may not be coverable. Either it
                // succeeds, or it is refused and changes nothing.
                1 => $this->sequentialDebit($ledger, random_int(1, $spendable + 2_000)),

                // Parallel debit pair sized so both cannot win: each is more
                // than half the spendable balance. Exactly one must succeed.
                2 => $this->parallelDebitPair($spendable > 0 ? intdiv($spendable, 2) + 1 : 1),

                // Occasionally place or lift a reservation to move the
                // spendable/balance gap around under the invariant.
                3 => $this->adjustReservation(),
            };

            $this->assertInvariant();
        }
    }

    private function sequentialDebit(LedgerService $ledger, int $amount): void
    {
        try {
            $ledger->debit($this->wallet, Cents::from($amount), 'withdrawal');
        } catch (InsufficientFundsException) {
            // Expected whenever the amount exceeds spendable; the invariant
            // check after the call confirms nothing moved.
        }
    }

    /**
     * Two debits of $amount racing on the wallet, interleaved deterministically.
     *
     * The loser reads its snapshot before the winner commits; with 2 * $amount
     * exceeding the spendable balance, exactly one can succeed. This is the C-1
     * scenario fired repeatedly inside the property run.
     */
    private function parallelDebitPair(int $amount): void
    {
        $ledger = app(LedgerService::class);

        DB::connection(self::RACER)->beginTransaction();
        // Establish the loser's snapshot.
        DB::connection(self::RACER)->table('wallets')->where('id', $this->wallet->id)->first();

        $this->runDebitOn('mysql', $ledger, $amount);
        $this->runDebitOn(self::RACER, $ledger, $amount);

        DB::connection(self::RACER)->commit();
    }

    private function runDebitOn(string $connection, LedgerService $ledger, int $amount): void
    {
        $previousDefault = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        try {
            $ledger->debit($this->wallet, Cents::from($amount), 'withdrawal');
        } catch (InsufficientFundsException) {
            // One of the pair is expected to lose.
        } finally {
            DB::setDefaultConnection($previousDefault);
        }
    }

    private function adjustReservation(): void
    {
        $balance = (int) DB::table('wallets')->where('id', $this->wallet->id)->value('balance_cents');
        // Reservations are a hold inside the balance; keep it within range so
        // the CHECK (reserved <= balance) is never the thing under test here.
        $reserved = $balance > 0 ? random_int(0, $balance) : 0;

        DB::table('wallets')->where('id', $this->wallet->id)->update(['reserved_cents' => $reserved]);
    }

    private function spendable(): int
    {
        $row = DB::table('wallets')->where('id', $this->wallet->id)->first();

        return (int) $row->balance_cents - (int) $row->reserved_cents;
    }

    private function assertInvariant(): void
    {
        $balance = (int) DB::table('wallets')->where('id', $this->wallet->id)->value('balance_cents');
        $reserved = (int) DB::table('wallets')->where('id', $this->wallet->id)->value('reserved_cents');

        $credits = (int) DB::table('ledger_entries')
            ->where('wallet_id', $this->wallet->id)->where('direction', 'credit')->sum('amount_cents');
        $debits = (int) DB::table('ledger_entries')
            ->where('wallet_id', $this->wallet->id)->where('direction', 'debit')->sum('amount_cents');

        $this->assertSame($credits - $debits, $balance, 'balance_cents must equal SUM(credits) - SUM(debits).');
        $this->assertGreaterThanOrEqual(0, $balance, 'balance_cents must never go negative.');
        $this->assertGreaterThanOrEqual(0, $balance - $reserved, 'spendable (balance - reserved) must never go negative.');
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
