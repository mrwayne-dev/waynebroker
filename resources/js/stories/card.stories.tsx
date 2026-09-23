import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

/**
 * Card under the Gotham token layer.
 *
 * What to look for: carbon surface against the void page, a 6px radius, a
 * hairline border doing the structural work, and no shadow at all.
 */
export const Default = () => (
    <Card className="max-w-sm">
        <CardHeader>
            <CardTitle>Wallet</CardTitle>
            <CardDescription>Available to withdraw</CardDescription>
        </CardHeader>
        <CardContent>
            {/* Figures use the numeric utility and the largest step of the
                type scale; the currency mark stays at body size so the
                number is what the eye lands on. */}
            <p className="numeric text-3xl text-ink-primary">
                <span className="text-ink-secondary">$</span>12,480.00
            </p>
        </CardContent>
        <CardFooter className="gap-3">
            <Button>Deposit</Button>
            <Button variant="outline">Withdraw</Button>
        </CardFooter>
    </Card>
);

/**
 * Gain and loss are reserved for money events (Section 15.1): green is not a
 * generic success colour anywhere in this product.
 */
export const SignalColours = () => (
    <div className="flex flex-wrap gap-4">
        <Card className="min-w-48">
            <CardHeader>
                <CardDescription>Realised today</CardDescription>
                <CardTitle className="numeric text-2xl text-signal-gain">
                    +$248.10
                </CardTitle>
            </CardHeader>
        </Card>
        <Card className="min-w-48">
            <CardHeader>
                <CardDescription>Realised today</CardDescription>
                <CardTitle className="numeric text-2xl text-signal-loss">
                    -$96.40
                </CardTitle>
            </CardHeader>
        </Card>
    </div>
);
