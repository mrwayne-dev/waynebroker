<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Domains\Wallet\Exceptions\InsufficientFundsException;
use App\Domains\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\NaiveWithdrawal;
use Tests\TestCase;

/**
 * Maveren audit finding C-1: the concurrent-withdrawal lost update.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * This test deliberately does NOT use RefreshDatabase.
 *
 * RefreshDatabase wraps each test in a single transaction on the default
 * connection and rolls it back at the end. That is exactly the wrong tool for
 * a concurrency test: a second connection cannot see rows the first connection
 * has not committed, so the whole scenario — one writer reading a balance
 * before another writer commits a change to it — cannot even be set up inside
 * one uncommitted transaction. So this class commits real rows and cleans them
 * up by hand in setUp/tearDown.
 * ──────────────────────────────────────────────────────────────────────────
 *
 * The race, made deterministic without sleeps or retries.
 *
 * C-1 is a lost update: two withdrawals each read the same starting balance
 * before either writes, so both pass their guard and both debit. The essence
 * of the defect is a NON-LOCKING read feeding a later write. The essence of the
 * fix is a LOCKING read (SELECT ... FOR UPDATE) that forces the second writer
 * to see the first writer's committed result.
 *
 * Both are reproduced here with two real connections and MySQL's REPEATABLE
 * READ snapshot semantics — no timing, no threads:
 *
 *   1. The LOSER opens a transaction on a second connection and takes a
 *      consistent-read snapshot while the balance is still 100.
 *   2. The WINNER performs a full withdrawal on the default connection and
 *      commits, leaving the balance at 0.
 *   3. The LOSER performs its withdrawal inside its stale-snapshot transaction.
 *
 * A non-locking read (NaiveWithdrawal) sees the snapshot's 100, passes its
 * guard, and writes — a second successful withdrawal, the lost update. This
 * commit uses NaiveWithdrawal, so the test is RED: both withdrawals succeed.
 * When LedgerService lands it is repointed there and the locking read makes the
 * loser read the committed 0 and refuse — green.
 *
 * innodb_lock_wait_timeout is pinned to 1s on both connections so that if a
 * future change ever made these operations block on each other, the test fails
 * in a second rather than hanging — the determinism here does not rely on it,
 * it is a seatbelt.
 */
final class ConcurrentWithdrawalRaceTest extends TestCase
{
    private const START_BALANCE = 100;

    private const RACER = 'wallet_racer';

    private User $user;

    private Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ledger_entries')) {
            Artisan::call('migrate', ['--force' => true]);
        }

        $this->resetTables();

        // A second connection to the same testing database. It is a distinct
        // PDO session, so its transaction and MVCC snapshot are independent of
        // the default connection's — which is the whole point.
        config()->set('database.connections.'.self::RACER, config('database.connections.mysql'));
        DB::purge(self::RACER);

        foreach (['mysql', self::RACER] as $connection) {
            DB::connection($connection)->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection($connection)->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }

        $this->user = User::factory()->create();
        $this->wallet = Wallet::query()->where('user_id', $this->user->id)->sole();

        // Seed the committed starting state the way it would exist in
        // production, by hand: one credit entry and a wallet balance of 100.
        // No service is used here on purpose — LedgerService does not exist yet,
        // and the setup must not depend on the thing under test.
        $head = hash('sha256', 'opening|'.$this->wallet->id);

        DB::table('ledger_entries')->insert([
            'wallet_id' => $this->wallet->id,
            'direction' => 'credit',
            'amount_cents' => self::START_BALANCE,
            'kind' => 'deposit',
            'reference_type' => null,
            'reference_id' => null,
            'metadata' => null,
            'previous_hash' => '',
            'hash' => $head,
            'idempotency_key' => null,
            'created_at' => now(),
        ]);

        DB::table('wallets')->where('id', $this->wallet->id)->update([
            'balance_cents' => self::START_BALANCE,
            'hash_head' => $head,
            'version' => 1,
        ]);
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

    public function test_concurrent_withdrawals_do_not_both_succeed(): void
    {
        $startingVersion = (int) DB::table('wallets')->where('id', $this->wallet->id)->value('version');

        // (1) The loser opens its transaction and fixes its read snapshot while
        //     the balance is still 100. A plain read establishes the InnoDB
        //     consistent-read view; every non-locking read it makes afterwards
        //     will see this 100 regardless of what anyone else commits.
        DB::connection(self::RACER)->beginTransaction();
        $snapshot = (int) DB::connection(self::RACER)->table('wallets')->where('id', $this->wallet->id)->value('balance_cents');
        $this->assertSame(self::START_BALANCE, $snapshot, 'Loser should start from a balance of 100.');

        // (2) The winner withdraws the whole balance on the default connection
        //     and commits. Balance is now 0 for anyone reading the latest row.
        $winner = $this->attemptWithdrawal('mysql', self::START_BALANCE);

        // (3) The loser withdraws inside its stale snapshot.
        $loser = $this->attemptWithdrawal(self::RACER, self::START_BALANCE);

        DB::connection(self::RACER)->commit();

        $succeeded = array_filter([$winner, $loser], static fn (array $o): bool => $o['succeeded']);
        $refused = array_filter([$winner, $loser], static fn (array $o): bool => $o['insufficientFunds']);

        // The C-1 assertion. A lost update is "both withdrawals succeeded"; the
        // fix is "exactly one did, and the other was refused for insufficient
        // funds". With NaiveWithdrawal both succeed and this fails — which is
        // the point of the red commit.
        $this->assertCount(1, $succeeded, 'Exactly one withdrawal must succeed; two succeeding is the C-1 lost update.');
        $this->assertCount(1, $refused, 'The losing withdrawal must be refused, not silently applied.');

        // The refusal must come from the service guard, not the database CHECK.
        // attemptWithdrawal only records insufficientFunds for an
        // InsufficientFundsException; a QueryException from the CHECK constraint
        // would escape it and fail this test, which is exactly what we want — if
        // the CHECK ever fires, the guard was bypassed.
        $this->assertTrue($loser['insufficientFunds'], 'The loser must throw InsufficientFundsException (the guard), never a QueryException (the CHECK).');
        $this->assertTrue($winner['succeeded'], 'The winner, reading first under the lock, must succeed.');

        // The wallet moved exactly once: one debit applied, one refused before
        // it could write.
        $this->assertSame(
            $startingVersion + 1,
            (int) DB::table('wallets')->where('id', $this->wallet->id)->value('version'),
            'wallets.version must advance by exactly one — one committed mutation.',
        );

        $this->assertSame(
            1,
            DB::table('ledger_entries')->where('wallet_id', $this->wallet->id)->where('direction', 'debit')->count(),
            'Exactly one debit entry must exist for the wallet.',
        );

        $this->assertSame(
            0,
            (int) DB::table('wallets')->where('id', $this->wallet->id)->value('balance_cents'),
            'The balance must settle at 0 — the one successful withdrawal, and no overdraft.',
        );
    }

    /**
     * Run a withdrawal on the named connection and report the outcome.
     *
     * The connection is made the default for the duration of the call so the
     * withdrawal — whichever one this version of the test uses — runs its reads
     * and writes there. The loser's call therefore executes inside the
     * stale-snapshot transaction already open on the racer connection.
     *
     * @return array{succeeded: bool, insufficientFunds: bool}
     */
    private function attemptWithdrawal(string $connection, int $amount): array
    {
        $previousDefault = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        try {
            // The naive read-then-write that reproduces C-1. The next commit but
            // one swaps this for LedgerService::debit and the test turns green.
            (new NaiveWithdrawal)->withdraw($this->wallet, $amount);

            return ['succeeded' => true, 'insufficientFunds' => false];
        } catch (InsufficientFundsException) {
            return ['succeeded' => false, 'insufficientFunds' => true];
        } finally {
            DB::setDefaultConnection($previousDefault);
        }
    }

    private function resetTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        // TRUNCATE rather than DELETE: it does not fire the ledger_entries
        // append-only trigger, and it resets the auto-increment so hashes in one
        // test cannot collide with another's by sharing an id.
        DB::table('ledger_entries')->truncate();
        DB::table('wallets')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
