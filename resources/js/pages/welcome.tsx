import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { login } from '@/routes';

/**
 * The public landing surface.
 *
 * Deliberately one screen with one action. Plan Section 15.7 asks for short,
 * institutional, present-tense copy and no hype, so there is no feature grid,
 * no testimonial, no statistics band — and no yield figures. The plan's rates
 * are real product terms, but a rate on an unauthenticated marketing page is a
 * promise, and this page makes none.
 *
 * Motion is the single entrance gesture allowed by Section 15.4: fade plus an
 * 8px rise, once, on load. `motion-reduce` drops it to a static state.
 */
export default function Welcome() {
    return (
        <>
            <Head title="Gotham Investments" />

            <div className="flex min-h-svh flex-col bg-background px-6 py-10 text-foreground">
                <main className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-2xl opacity-100 transition-[opacity,translate] duration-420 ease-gotham motion-reduce:transition-none starting:translate-y-2 starting:opacity-0">
                        {/* The reserved gesture from Section 15.2: the display
                            face, heaviest weight, largest step, tight tracking.
                            Nothing else in the system is allowed to do this. */}
                        <h1 className="text-4xl font-bold tracking-tight text-ink-primary sm:text-5xl">
                            Gotham Investments
                        </h1>

                        <p className="mt-4 max-w-lg text-lg text-ink-secondary">
                            Fixed-term investment plans and a proprietary market
                            terminal, in one account.
                        </p>

                        <div className="mt-10">
                            <Button asChild size="lg">
                                <Link href={login()}>Sign in</Link>
                            </Button>
                        </div>
                    </div>
                </main>

                <footer className="mx-auto flex w-full max-w-2xl items-center gap-3 border-t border-border pt-6 text-sm text-ink-muted">
                    <span>waynebroker.mgbah.dev</span>
                    <span aria-hidden="true">·</span>
                    {/* Every number in the product goes through the numeric
                        utility — JetBrains Mono with tabular figures. */}
                    <span className="numeric">2026</span>
                </footer>
            </div>
        </>
    );
}
