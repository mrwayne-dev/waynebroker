<?php

use App\Domains\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The database refuses an impossible balance, whatever the application does.
 *
 * Every write here goes through the query builder rather than the model, on
 * purpose. $fillable and the ledger service are the first line; these tests are
 * about the second one. Maveren's C-1 overdraft race got through because the
 * only guard was an application-level balance check, and two concurrent
 * withdrawals both read the balance before either wrote. A CHECK constraint
 * cannot be raced.
 */
function walletFor(?User $user = null): Wallet
{
    // Creating a user creates their wallet, so this reads the one the
    // application made rather than inventing a second one the unique index
    // would refuse anyway.
    return Wallet::query()->where('user_id', ($user ?? User::factory()->create())->id)->sole();
}

test('a new wallet starts empty', function () {
    $wallet = walletFor()->refresh();

    expect($wallet->balance_cents)->toBe(0)
        ->and($wallet->reserved_cents)->toBe(0)
        ->and($wallet->hash_head)->toBe('')
        ->and($wallet->version)->toBe(0);
});

test('a negative balance is refused by the database', function () {
    $wallet = walletFor();

    expect(fn () => DB::table('wallets')->where('id', $wallet->id)->update(['balance_cents' => -1]))
        ->toThrow(QueryException::class);

    expect($wallet->refresh()->balance_cents)->toBe(0);
});

test('a negative reservation is refused by the database', function () {
    $wallet = walletFor();

    expect(fn () => DB::table('wallets')->where('id', $wallet->id)->update(['reserved_cents' => -1]))
        ->toThrow(QueryException::class);
});

test('a reservation larger than the balance is refused by the database', function () {
    // The gross model's load-bearing constraint. If reserved could exceed
    // balance, spendable (balance - reserved) would go negative while the
    // non-negative balance check still passed, and the wallet would be
    // overdrawn through a column that never went below zero.
    $wallet = walletFor();

    DB::table('wallets')->where('id', $wallet->id)->update(['balance_cents' => 10_000]);

    expect(fn () => DB::table('wallets')->where('id', $wallet->id)->update(['reserved_cents' => 10_001]))
        ->toThrow(QueryException::class);

    // The boundary is allowed: a fully committed balance is a legitimate state.
    DB::table('wallets')->where('id', $wallet->id)->update(['reserved_cents' => 10_000]);

    expect($wallet->refresh()->reserved_cents)->toBe(10_000);
});

test('lowering the balance below an existing reservation is refused', function () {
    // The constraint is on the row, not on the statement that happens to set
    // reserved_cents, so it holds when the other side of the comparison moves.
    $wallet = walletFor();

    DB::table('wallets')->where('id', $wallet->id)->update(['balance_cents' => 10_000]);
    DB::table('wallets')->where('id', $wallet->id)->update(['reserved_cents' => 10_000]);

    expect(fn () => DB::table('wallets')->where('id', $wallet->id)->update(['balance_cents' => 9_999]))
        ->toThrow(QueryException::class);
});

test('a large balance survives a database round trip as an integer', function () {
    // intdiv, not PHP_INT_MAX / 2: the division operator returns a float
    // (4.6116860184274E+18) which has already lost its low-order digits before
    // it reaches the database. That is finding M-19 in two characters, and
    // writing it the wrong way here would hide the thing being tested.
    $amount = intdiv(PHP_INT_MAX, 2);

    $wallet = walletFor();
    DB::table('wallets')->where('id', $wallet->id)->update(['balance_cents' => $amount]);

    $loaded = Wallet::query()->findOrFail($wallet->id);

    expect($loaded->balance_cents)->toBeInt()->toBe($amount)
        ->and((string) $loaded->balance_cents)->toBe('4611686018427387903');
});

test('the balance is an integer even when the driver hands back strings', function () {
    // This is the Checkpoint 3 bug reproduced rather than described. There,
    // users.session_version had no cast, the suite was green, and the defect
    // was invisible because the test environment never produced the string
    // form. This install runs native prepares, so BIGINT already arrives as a
    // PHP int and the cast above is doing nothing observable — a test that only
    // asserts the happy configuration cannot fail, which makes it worthless as
    // a guard.
    //
    // So the hazardous configuration is forced. PDO::ATTR_STRINGIFY_FETCHES is
    // what emulated prepares effectively do to a BIGINT, and it is one option
    // array away from being this application's reality on another host. Under
    // it, a wallet without the integer cast yields a string, and every
    // comparison the ledger service makes against the balance becomes a string
    // comparison that is false against its own value.
    $amount = intdiv(PHP_INT_MAX, 2);

    $wallet = walletFor();
    DB::table('wallets')->where('id', $wallet->id)->update(['balance_cents' => $amount]);

    $pdo = DB::connection()->getPdo();
    $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

    try {
        // Proof the hazard is actually present in this test, so that a future
        // driver change cannot turn the assertion below into a no-op quietly.
        expect(DB::table('wallets')->where('id', $wallet->id)->value('balance_cents'))->toBeString();

        $loaded = Wallet::query()->findOrFail($wallet->id);

        expect($loaded->balance_cents)->toBeInt()->toBe($amount)
            ->and($loaded->reserved_cents)->toBeInt()
            ->and($loaded->version)->toBeInt();
    } finally {
        // The connection is reused by the rest of the process.
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    }
});

test('the money columns are not mass assignable', function () {
    $wallet = walletFor();

    $wallet->fill([
        'balance_cents' => 500_00,
        'reserved_cents' => 100_00,
        'hash_head' => str_repeat('a', 64),
        'version' => 99,
    ])->save();

    expect($wallet->refresh()->balance_cents)->toBe(0)
        ->and($wallet->reserved_cents)->toBe(0)
        ->and($wallet->hash_head)->toBe('')
        ->and($wallet->version)->toBe(0);
});
