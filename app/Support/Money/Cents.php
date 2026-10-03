<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;
use TypeError;

/**
 * An amount of money, in minor units, that cannot be a float.
 *
 * Retires Maveren audit finding M-19. There, `users.balance` was DECIMAL(15,2)
 * and every amount passed through PHP floats on the way in and out:
 * `$balance - $amount` in api/backend/withdraw.php, `number_format($row['balance'], 2)`
 * on display, `floatval($_POST['amount'])` at the boundary. The audit found
 * fractions of a cent accumulating between a balance and the sum of its own
 * transactions, and no way to say which number was right.
 *
 * The constructor is private and `from` takes `mixed` rather than `int`. That
 * looks like a loss of type safety and is the opposite. `declare(strict_types=1)`
 * is a property of the file making the call, not the file declaring the
 * function, so a typed `int` parameter only throws for callers who happen to
 * have opted in — and in coercive mode PHP converts 1.5 to 1 with a deprecation
 * notice nobody reads. A money boundary cannot depend on every present and
 * future caller remembering a declare line, so the check is explicit and the
 * behaviour is the same from everywhere.
 */
final class Cents
{
    private function __construct(private readonly int $amount) {}

    /**
     * @param  bool  $allowNegative  Ledger entries and adjustments are signed;
     *                               balances and deposit amounts are not. The
     *                               default refuses a negative so that a sign
     *                               error has to be written down deliberately.
     */
    public static function from(mixed $amount, bool $allowNegative = false): self
    {
        if (is_float($amount)) {
            throw new TypeError(sprintf(
                'Money must be an integer number of cents; got the float %s. A float cannot hold a cent exactly (Maveren M-19) — cast at the boundary with (int) round($value * 100), once.',
                var_export($amount, true),
            ));
        }

        if (! is_int($amount)) {
            throw new TypeError(sprintf(
                'Money must be an integer number of cents; got %s. Numeric strings are refused too: a string is what arrives from a request, and converting it here would move the boundary into the value object.',
                get_debug_type($amount),
            ));
        }

        if (! $allowNegative && $amount < 0) {
            throw new InvalidArgumentException(sprintf(
                'Refusing the negative amount %d. Pass allowNegative: true where a signed amount is intended, such as a debit entry.',
                $amount,
            ));
        }

        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function toInt(): int
    {
        return $this->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }
}
