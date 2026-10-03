<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Inertia\Inertia;
use Inertia\Response;

class MfaSetupController extends Controller
{
    /**
     * The enrolment page an administrator is held at until their second factor
     * is confirmed.
     *
     * It reuses Fortify's TOTP flow rather than reimplementing it: the same
     * endpoints, the same ManageTwoFactor component the security settings page
     * uses. The difference is only that this one cannot be navigated away from
     * into the rest of /admin.
     */
    public function __invoke(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();

        return Inertia::render('admin/mfa-setup', [
            'twoFactorEnabled' => $request->authenticatedUser()->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => true,
        ]);
    }
}
