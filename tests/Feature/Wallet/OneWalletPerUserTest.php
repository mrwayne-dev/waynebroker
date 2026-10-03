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
 * This commit lands the first half failing: the database must refuse the second
 * row. The unique index that makes it pass, and the single creation path for the
 * first row, follow in the next commit.
 */
test('a user cannot hold two wallets', function () {
    $user = User::factory()->create();

    Wallet::create(['user_id' => $user->id]);

    expect(fn () => Wallet::create(['user_id' => $user->id]))
        ->toThrow(QueryException::class);

    expect(Wallet::query()->where('user_id', $user->id)->count())->toBe(1);
});
