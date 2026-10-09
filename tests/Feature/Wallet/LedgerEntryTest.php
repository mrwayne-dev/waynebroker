<?php

use App\Domains\Wallet\Exceptions\LedgerEntryIsImmutableException;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The ledger is append-only, proven at every layer that could break it.
 *
 * There are two guards and this file tests both, because they fail
 * differently and a reader needs to know which one caught a given mistake. The
 * model's own update()/delete() throw a LogicException before any SQL is built
 * — the common case, calling ->update() on a loaded entry. The database
 * triggers throw a QueryException and catch the writes that never touch the
 * model: a raw query-builder UPDATE or DELETE. If either layer were removed the
 * other would still hold, which is the point of having both.
 */
function ledgerWallet(): Wallet
{
    return Wallet::query()->where('user_id', User::factory()->create()->id)->sole();
}

function insertEntry(Wallet $wallet, array $overrides = []): LedgerEntry
{
    return LedgerEntry::query()->create(array_merge([
        'wallet_id' => $wallet->id,
        'direction' => 'credit',
        'amount_cents' => 100,
        'kind' => 'deposit',
        'previous_hash' => '',
        'hash' => hash('sha256', 'seed'.$wallet->id),
        'created_at' => now(),
    ], $overrides));
}

test('an entry can be inserted and read back', function () {
    $wallet = ledgerWallet();

    $entry = insertEntry($wallet, ['metadata' => ['note' => 'first']]);

    $loaded = LedgerEntry::query()->findOrFail($entry->id);

    expect($loaded->wallet_id)->toBe($wallet->id)
        ->and($loaded->direction)->toBe('credit')
        ->and($loaded->amount_cents)->toBe(100)
        ->and($loaded->kind)->toBe('deposit')
        ->and($loaded->metadata)->toBe(['note' => 'first'])
        ->and($loaded->created_at)->not->toBeNull();
});

test('a raw UPDATE on a ledger entry is refused by the database', function () {
    // The backstop trigger. This write never goes near the model, so only the
    // database can stop it — and it does, with SIGNAL SQLSTATE 45000 surfacing
    // as a QueryException.
    $wallet = ledgerWallet();
    $entry = insertEntry($wallet);

    expect(fn () => DB::table('ledger_entries')->where('id', $entry->id)->update(['amount_cents' => 1]))
        ->toThrow(QueryException::class);

    expect(LedgerEntry::query()->findOrFail($entry->id)->amount_cents)->toBe(100);
});

test('a raw DELETE on a ledger entry is refused by the database', function () {
    $wallet = ledgerWallet();
    $entry = insertEntry($wallet);

    expect(fn () => DB::table('ledger_entries')->where('id', $entry->id)->delete())
        ->toThrow(QueryException::class);

    expect(LedgerEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
});

test('the model refuses update() before the database is touched', function () {
    // The inner guard throws a LogicException, not a QueryException: the point
    // is that the mistake is caught at the call site, not several frames down in
    // the driver. The entry is unchanged because no statement ran.
    $wallet = ledgerWallet();
    $entry = insertEntry($wallet);

    expect(fn () => $entry->update(['amount_cents' => 1]))
        ->toThrow(LedgerEntryIsImmutableException::class);
});

test('the model refuses delete() before the database is touched', function () {
    $wallet = ledgerWallet();
    $entry = insertEntry($wallet);

    expect(fn () => $entry->delete())
        ->toThrow(LedgerEntryIsImmutableException::class);
});

test('amount_cents survives a database round trip as an integer', function () {
    // The same BIGINT discipline as the wallet (Checkpoint 4): a large value
    // must come back an int, not a string that compares false against itself in
    // a sum.
    $wallet = ledgerWallet();
    $amount = intdiv(PHP_INT_MAX, 2);

    $entry = insertEntry($wallet, ['amount_cents' => $amount]);

    $loaded = LedgerEntry::query()->findOrFail($entry->id);

    expect($loaded->amount_cents)->toBeInt()->toBe($amount)
        ->and((string) $loaded->amount_cents)->toBe('4611686018427387903');
});

test('a zero-value entry is refused by the database', function () {
    // CHECK amount_cents > 0: an entry that moves nothing explains nothing and
    // reconciles to nothing.
    $wallet = ledgerWallet();

    expect(fn () => insertEntry($wallet, ['amount_cents' => 0]))
        ->toThrow(QueryException::class);
});

test('an unknown kind is refused by the database', function () {
    // CHECK kind IN (...): the money vocabulary is controlled at the one layer
    // that cannot be talked round.
    $wallet = ledgerWallet();

    expect(fn () => insertEntry($wallet, ['kind' => 'not_a_real_kind']))
        ->toThrow(QueryException::class);
});
