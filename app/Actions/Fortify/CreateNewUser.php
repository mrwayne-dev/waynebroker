<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Domains\Identity\Roles;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // Registration grants exactly one role, and it is the least privileged
        // one. Maveren's H-9 is the reason this is written down rather than
        // assumed: its admin registration endpoint assigned super_admin to
        // anyone holding a shared invite code, which meant the code — not the
        // role column — decided who could rewrite the deposit addresses member
        // funds were sent to. Staff roles are granted deliberately by a
        // super_admin, never by a self-service path.
        $user->assignRole(Roles::MEMBER);

        return $user;
    }
}
