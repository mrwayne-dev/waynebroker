<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // A reset is the one action taken precisely because a password may be
        // in someone else's hands, so every existing session goes — including
        // any the attacker is holding. Nothing is preserved here: whoever just
        // reset signs in again with the new password.
        $user->forceFill([
            'password' => $input['password'],
            'session_version' => $user->session_version + 1,
        ])->save();
    }
}
