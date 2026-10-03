<?php

use App\Domains\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * Retires Maveren audit finding M-3.
 *
 * Maveren had no uniqueness on the wallet owner. api/backend/wallet.php created
 * a wallet row whenever it could not find one, and three code paths could reach
 * that branch, so a member who hit two of them inside the same request window
 * ended up with two wallets. Every subsequent read used whichever row the query
 * returned first, which meant the balance a member saw depended on row order.
 * The audit found two such accounts and could not establish which balance was
 * the real one.
 *
 * Both halves are covered: the database refuses a second row, and there is one
 * path that creates the first one.
 */
test('a user cannot hold two wallets', function () {
    // The first wallet arrives with the user, from UserObserver. The attempt
    // below is the lazy second insert Maveren's read paths made.
    $user = User::factory()->create();

    expect(Wallet::query()->where('user_id', $user->id)->count())->toBe(1);

    expect(fn () => Wallet::create(['user_id' => $user->id]))
        ->toThrow(QueryException::class);

    expect(Wallet::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('creating a user creates exactly one empty wallet', function () {
    $user = User::factory()->create();

    $wallet = Wallet::query()->where('user_id', $user->id)->sole();

    expect($wallet->balance_cents)->toBe(0)
        ->and($wallet->reserved_cents)->toBe(0)
        ->and($wallet->hash_head)->toBe('')
        ->and($wallet->version)->toBe(0);
});

test('registering through the form creates exactly one empty wallet', function () {
    // The path a real member takes, rather than the factory. CreateNewUser is
    // where Maveren would have needed the wallet and did not have it.
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'member@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'member@example.test')->sole();

    expect(Wallet::query()->count())->toBe(1);

    $wallet = Wallet::query()->where('user_id', $user->id)->sole();

    expect($wallet->balance_cents)->toBe(0)
        ->and($wallet->reserved_cents)->toBe(0);
});

test('the creation path is idempotent', function () {
    // A caller that cannot be sure whether the wallet exists must not have to
    // write its own lookup-then-insert, because that is the shape of the bug.
    $user = User::factory()->create();

    $first = Wallet::createForUser($user);
    $second = Wallet::createForUser($user);

    expect($second->id)->toBe($first->id)
        ->and(Wallet::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('deleting a user deletes their wallet', function () {
    // cascadeOnDelete, asserted rather than assumed: an orphaned wallet row
    // would hold a balance nobody owns and would block the user_id from being
    // reused by the unique index.
    $user = User::factory()->create();

    $user->delete();

    expect(Wallet::query()->where('user_id', $user->id)->exists())->toBeFalse();
});
