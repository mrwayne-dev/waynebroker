import { cn } from '@/lib/utils';

/**
 * The client half of the money contract.
 *
 * The integer cents arrive from the server unchanged and the split into major
 * and minor units is integer arithmetic, so there is no point at which a
 * division by 100 could lose a digit. Two implementations of the same format
 * can still drift apart, which is what the shared cases in
 * tests/fixtures/money-formatting.json exist to catch; both this suite and the
 * PHP one read that file.
 */
type MoneyProps = {
    /** The amount in minor units, exactly as the server sent it. */
    cents: number;
    withSymbol?: boolean;
    className?: string;
};

export const SYMBOL = '$';

export function formatCents(cents: number, withSymbol = false): string {
    // Loud rather than wrong. A non-integer here means the amount was divided
    // somewhere between the ledger and this component, and the digits are
    // already lost; rendering a plausible number would hide that.
    if (!Number.isInteger(cents)) {
        throw new TypeError(
            `Money must be an integer number of cents; got ${String(cents)}.`,
        );
    }

    // toLocaleString is pinned to en-US rather than left to the viewer's
    // locale. The server has one output, and a browser in de-DE would render
    // 1.234,56 against the same page's 1,234.56 from PHP.
    const negative = cents < 0;
    const absolute = Math.abs(cents);
    const major = Math.trunc(absolute / 100).toLocaleString('en-US');
    const minor = String(absolute % 100).padStart(2, '0');

    const sign = negative ? '-' : '';
    const symbol = withSymbol ? SYMBOL : '';

    // The sign goes outside the symbol: -$5.00 reads as a debit, $-5.00 reads
    // as a typo. Matches Usd::formatWithSymbol.
    return `${sign}${symbol}${major}.${minor}`;
}

export default function Money({
    cents,
    withSymbol = false,
    className,
}: MoneyProps) {
    return (
        <span className={cn('numeric', className)} data-slot="money">
            {formatCents(cents, withSymbol)}
        </span>
    );
}
