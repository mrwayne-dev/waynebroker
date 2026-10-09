<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Exceptions;

use RuntimeException;

/**
 * A debit was refused because the wallet could not cover it.
 *
 * This is the service's own guard firing, not the database's. The distinction
 * is the whole point of Maveren finding C-1: there the only thing standing
 * between two concurrent withdrawals and a negative balance was a balance read
 * taken outside the transaction, and it did not stand. Here the guard runs
 * inside the row lock, so it sees the balance no other writer can be mid-change
 * on, and a debit that would overdraw is turned away before any ledger row is
 * written.
 *
 * The CHECK constraint on wallets.balance_cents is the backstop, not this. If a
 * debit ever surfaces as a QueryException from that constraint rather than as
 * this exception, the guard was bypassed and the test that asserts this type —
 * not QueryException — is what catches the regression.
 */
final class InsufficientFundsException extends RuntimeException
{
    public static function forDebit(int $available, int $amount): self
    {
        return new self(sprintf(
            'Refusing a debit of %d cents against %d cents available (balance minus reserved). The ledger service guards this before writing; a negative balance is not reachable through it.',
            $amount,
            $available,
        ));
    }
}
