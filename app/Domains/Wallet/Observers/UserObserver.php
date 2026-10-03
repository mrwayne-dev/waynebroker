<?php

namespace App\Domains\Wallet\Observers;

use App\Domains\Wallet\Models\Wallet;
use App\Models\User;

/**
 * Every member has a wallet from the moment they exist.
 *
 * The other half of Maveren M-3. There, three read paths created a wallet
 * lazily when a lookup missed, which is what made a duplicate possible at all.
 * Creating it once, here, means no read path ever has a reason to write — and a
 * read path that cannot create cannot create a duplicate.
 */
class UserObserver
{
    public function created(User $user): void
    {
        Wallet::createForUser($user);
    }
}
