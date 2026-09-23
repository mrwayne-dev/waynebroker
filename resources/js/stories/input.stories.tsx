import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Input under the Gotham token layer.
 *
 * What to look for: a hairline border in --surface-line and no shadow, a
 * transparent fill so the input inherits whatever surface it sits on, and a
 * focus ring in --accent-brass-hot.
 */
export const Default = () => (
    <div className="grid max-w-sm gap-2">
        <Label htmlFor="email">Email</Label>
        <Input id="email" type="email" placeholder="you@example.com" />
    </div>
);

/**
 * Any field holding a number gets the `numeric` utility: JetBrains Mono with
 * tabular figures, so digits do not shift width as they change.
 */
export const Numeric = () => (
    <div className="grid max-w-sm gap-2">
        <Label htmlFor="volume">Volume</Label>
        <Input
            id="volume"
            className="numeric"
            defaultValue="1.00"
            inputMode="decimal"
        />
    </div>
);

export const Invalid = () => (
    <div className="grid max-w-sm gap-2">
        <Label htmlFor="amount">Amount</Label>
        <Input id="amount" aria-invalid defaultValue="0" className="numeric" />
        <p className="text-sm text-signal-loss">
            Enter an amount above the plan minimum.
        </p>
    </div>
);

export const Disabled = () => (
    <div className="grid max-w-sm gap-2">
        <Label htmlFor="locked">Account reference</Label>
        <Input id="locked" disabled defaultValue="GTM-000412" />
    </div>
);
