<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Wallet\Exceptions\InsufficientFundsException;
use App\Domains\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * Maveren's withdrawal, reproduced exactly. A permanent anti-pattern reference.
 *
 * This is the shape of api/backend/wallet.php:524-549 (audit finding C-1): the
 * balance is read, a guard compares it in PHP, and the balance is written back
 * as an absolute value — with no row lock and no transaction spanning the read
 * and the write. Two of these running at once each read the same starting
 * balance, each pass the guard, and each write; the second write overwrites the
 * first, so two withdrawals leave the balance as if one had happened. That is
 * the lost update C-1 describes, and driving the balance negative is only its
 * most visible form.
 *
 * It lives in the repo on purpose. ConcurrentWithdrawalRaceTest runs against
 * this first, red, to show the defect is real, then against LedgerService,
 * green, to show the fix closes it. Deleting this helper would delete the
 * evidence that the test tests anything. It is never wired into application
 * code — it has no route, no binding, no caller outside the test that documents
 * why it must never be written this way.
 */
final class NaiveWithdrawal
{
    public function withdraw(Wallet $wallet, int $amount): void
    {
        $balance = DB::table('wallets')
            ->where('id', $wallet->id)
            ->value('balance_cents');

        if ($balance < $amount) {
            throw new InsufficientFundsException(
                'Naive guard refused the withdrawal — but this guard is the bug, not the fix: it reads outside any lock.'
            );
        }

        DB::table('wallets')
            ->where('id', $wallet->id)
            ->update(['balance_cents' => $balance - $amount]);
    }
}
