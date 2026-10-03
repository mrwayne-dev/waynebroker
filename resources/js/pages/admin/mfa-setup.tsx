import { Head } from '@inertiajs/react';

import ManageTwoFactor from '@/components/manage-two-factor';

type Props = {
    twoFactorEnabled: boolean;
    requiresConfirmation: boolean;
};

/**
 * The gate every administrator passes through once.
 *
 * Deliberately has no navigation: EnforceAdminMfa redirects back here from any
 * other /admin route until the second factor is confirmed, so offering links
 * out would only produce a loop the visitor has to discover for themselves.
 * The way forward is to finish enrolment; the way out is to sign out.
 */
export default function AdminMfaSetup({
    twoFactorEnabled,
    requiresConfirmation,
}: Props) {
    return (
        <>
            <Head title="Set up two-factor authentication" />

            <div className="flex min-h-svh flex-col items-center justify-center bg-background px-6 py-12 text-foreground">
                <div className="w-full max-w-xl space-y-8">
                    <div className="space-y-3">
                        <h1 className="font-display text-2xl font-bold tracking-tight text-ink-primary">
                            Two-factor authentication required
                        </h1>
                        <p className="text-ink-secondary">
                            Administrator accounts can move member funds and
                            change where deposits are sent. Confirm a second
                            factor to continue.
                        </p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-6">
                        <ManageTwoFactor
                            canManageTwoFactor
                            twoFactorEnabled={twoFactorEnabled}
                            requiresConfirmation={requiresConfirmation}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
