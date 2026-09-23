import { Button } from '@/components/ui/button';

/**
 * Button under the Gotham token layer.
 *
 * What to look for: `default` is brass with void text and lifts to
 * --accent-brass-hot on hover, not to a translucent brass. Nothing carries a
 * shadow — Section 15.3 keeps the system's one shadow for modals.
 */
export const Variants = () => (
    <div className="flex flex-wrap items-center gap-4">
        <Button>Open position</Button>
        <Button variant="secondary">Cancel</Button>
        <Button variant="outline">Details</Button>
        <Button variant="ghost">Dismiss</Button>
        <Button variant="destructive">Close position</Button>
        <Button variant="link">Terms</Button>
    </div>
);

export const Sizes = () => (
    <div className="flex flex-wrap items-center gap-4">
        <Button size="sm">Small</Button>
        <Button>Default</Button>
        <Button size="lg">Large</Button>
    </div>
);

/** Disabled state is opacity-only, so the brass still reads as the primary. */
export const Disabled = () => (
    <div className="flex flex-wrap items-center gap-4">
        <Button disabled>Open position</Button>
        <Button variant="outline" disabled>
            Details
        </Button>
    </div>
);
