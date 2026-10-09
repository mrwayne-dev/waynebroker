<?php

use App\Domains\Wallet\Exceptions\InsufficientFundsException;
use App\Domains\Wallet\LedgerService;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use App\Models\User;
use App\Support\Money\Cents;
use Illuminate\Support\Facades\DB;

/**
 * LedgerService: the single write path, read round by round.
 *
 * These cover the service's own contract — the hash chain, idempotency, the
 * guard — in isolation. The concurrency proof that this guard actually holds
 * two writers apart lives in ConcurrentWithdrawalRaceTest; here each test is a
 * single caller checking that one call does exactly what it claims and nothing
 * more.
 */
function serviceWallet(): Wallet
{
    return Wallet::query()->where('user_id', User::factory()->create()->id)->sole();
}

function ledger(): LedgerService
{
    return app(LedgerService::class);
}

test('a credit writes an entry, moves the balance, and extends the chain', function () {
    $wallet = serviceWallet();

    $entry = ledger()->credit($wallet, Cents::from(500), 'deposit');

    $wallet->refresh();

    expect($entry->direction)->toBe('credit')
        ->and($entry->amount_cents)->toBe(500)
        ->and($entry->previous_hash)->toBe('') // first entry on the wallet
        ->and($wallet->balance_cents)->toBe(500)
        ->and($wallet->hash_head)->toBe($entry->hash)
        ->and($wallet->version)->toBe(1);
});

test('a debit writes an entry, lowers the balance, and extends the chain', function () {
    $wallet = serviceWallet();

    $credit = ledger()->credit($wallet, Cents::from(500), 'deposit');
    $debit = ledger()->debit($wallet, Cents::from(200), 'withdrawal');

    $wallet->refresh();

    expect($debit->direction)->toBe('debit')
        ->and($debit->amount_cents)->toBe(200)
        ->and($debit->previous_hash)->toBe($credit->hash) // chained onto the credit
        ->and($wallet->balance_cents)->toBe(300)
        ->and($wallet->hash_head)->toBe($debit->hash)
        ->and($wallet->version)->toBe(2);
});

test('insufficient funds throws before any entry is written', function () {
    $wallet = serviceWallet();
    ledger()->credit($wallet, Cents::from(100), 'deposit');

    $before = LedgerEntry::query()->where('wallet_id', $wallet->id)->count();

    expect(fn () => ledger()->debit($wallet, Cents::from(101), 'withdrawal'))
        ->toThrow(InsufficientFundsException::class);

    $after = LedgerEntry::query()->where('wallet_id', $wallet->id)->count();

    expect($after)->toBe($before)
        ->and($wallet->refresh()->balance_cents)->toBe(100)
        ->and($wallet->version)->toBe(1); // unchanged by the refused debit
});

test('a debit may not draw on reserved funds', function () {
    // Gross model (R6): spendable is balance - reserved. A hold makes part of
    // the balance unavailable to a debit even though the balance covers it.
    $wallet = serviceWallet();
    ledger()->credit($wallet, Cents::from(1_000), 'deposit');

    // Place a hold directly; reservations are a separate concern, this just
    // sets the state the guard must respect.
    DB::table('wallets')->where('id', $wallet->id)->update(['reserved_cents' => 800]);

    expect(fn () => ledger()->debit($wallet, Cents::from(300), 'withdrawal'))
        ->toThrow(InsufficientFundsException::class);

    // 200 is spendable (1000 - 800) and goes through.
    ledger()->debit($wallet, Cents::from(200), 'withdrawal');

    expect($wallet->refresh()->balance_cents)->toBe(800);
});

test('two credits with the same idempotency key produce one entry', function () {
    $wallet = serviceWallet();

    $first = ledger()->credit($wallet, Cents::from(250), 'deposit', idempotencyKey: 'ipn-abc');
    $second = ledger()->credit($wallet, Cents::from(250), 'deposit', idempotencyKey: 'ipn-abc');

    expect($second->id)->toBe($first->id)
        ->and(LedgerEntry::query()->where('wallet_id', $wallet->id)->count())->toBe(1)
        ->and($wallet->refresh()->balance_cents)->toBe(250) // credited once, not twice
        ->and($wallet->version)->toBe(1);
});

// The concurrent-idempotency case (two deliveries racing on the same key)
// needs committed state visible across two connections, which RefreshDatabase
// cannot provide; it lives in LedgerIdempotencyConcurrencyTest, which opts out
// of RefreshDatabase for the same reason the withdrawal race test does.

test('the hash chain verifies newest to oldest', function () {
    $wallet = serviceWallet();

    // Ten movements of mixed direction, each chaining onto the last.
    ledger()->credit($wallet, Cents::from(1_000), 'deposit');
    for ($i = 0; $i < 9; $i++) {
        $i % 2 === 0
            ? ledger()->debit($wallet, Cents::from(10 + $i), 'withdrawal')
            : ledger()->credit($wallet, Cents::from(10 + $i), 'bonus');
    }

    $entries = LedgerEntry::query()
        ->where('wallet_id', $wallet->id)
        ->orderByDesc('id')
        ->get();

    expect($entries)->toHaveCount(10);

    foreach ($entries as $entry) {
        $recomputed = hash('sha256',
            $entry->previous_hash
            .'|'.$entry->direction
            .'|'.$entry->amount_cents
            .'|'.$entry->kind
            .'|'.($entry->reference_type ?? '')
            .'|'.($entry->reference_id ?? '')
            .'|'.$entry->wallet_id
            .'|'.$entry->created_at->timestamp,
        );

        expect($entry->hash)->toBe($recomputed);
    }

    // And the links actually connect: each entry's previous_hash is the hash of
    // the one before it, back to the empty-string genesis.
    $ascending = $entries->reverse()->values();
    $expectedPrevious = '';
    foreach ($ascending as $entry) {
        expect($entry->previous_hash)->toBe($expectedPrevious);
        $expectedPrevious = $entry->hash;
    }

    expect($wallet->refresh()->hash_head)->toBe($ascending->last()->hash);
});
