import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import contract from '../fixtures/money-formatting.json';

import Money, { formatCents } from '@/components/money';

/**
 * The client half of Maveren M-19.
 *
 * The contract file is read from disk rather than duplicated, and the PHP suite
 * reads the same one. Maveren's dashboard disagreed with its own server by a
 * cent on amounts ending in 5 because each side had its own formatting code;
 * two tests over one file is what prevents that quietly recurring.
 */
describe('formatCents', () => {
    it('has cases to check', () => {
        // Guards the guard: an empty or renamed fixture would make every
        // assertion below vacuous and the suite would still be green.
        expect(contract.cases.length).toBeGreaterThan(10);
    });

    it.each(contract.cases)(
        'formats $cents as $plain',
        ({ cents, plain, withSymbol }) => {
            expect(formatCents(cents)).toBe(plain);
            expect(formatCents(cents, true)).toBe(withSymbol);
        },
    );

    it('refuses a non-integer amount', () => {
        // The same rule as Cents::from. A float here means the amount was
        // divided somewhere between the ledger and the screen.
        expect(() => formatCents(1.5)).toThrow(TypeError);
        expect(() => formatCents(10.5)).toThrow(TypeError);
        expect(() => formatCents(Number.NaN)).toThrow(TypeError);
    });
});

describe('Money', () => {
    it('renders the amount in the monospace numeral face', () => {
        render(<Money cents={123456} />);

        const money = screen.getByText('1,234.56');

        expect(money).toHaveAttribute('data-slot', 'money');
        // The numeric utility carries JetBrains Mono and tabular figures, so
        // columns of balances line up. ADR-0004.
        expect(money).toHaveClass('numeric');
    });

    it('renders the symbol when asked', () => {
        render(<Money cents={-123456} withSymbol />);

        expect(screen.getByText('-$1,234.56')).toBeInTheDocument();
    });
});
