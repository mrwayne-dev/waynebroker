import { render, screen } from '@testing-library/react';
import { expect, it } from 'vitest';

import { Button } from '@/components/ui/button';

/**
 * Phase 0 smoke test: proves the Vitest + jsdom + Testing Library pipeline
 * runs and that the restyled primitive still behaves like a shadcn component.
 *
 * It deliberately asserts structure, not colour. Computed colour comes from the
 * token layer, which jsdom does not evaluate — the Playwright smoke test checks
 * the rendered palette against a real browser instead.
 */
it('renders a button carrying the shadcn slot contract', () => {
    render(<Button>Open position</Button>);

    const button = screen.getByRole('button', { name: 'Open position' });

    expect(button).toBeInTheDocument();
    expect(button).toHaveAttribute('data-slot', 'button');
});

it('renders as a child element when asChild is set', () => {
    render(
        <Button asChild>
            <a href="/login">Sign in</a>
        </Button>,
    );

    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute(
        'data-slot',
        'button',
    );
});
