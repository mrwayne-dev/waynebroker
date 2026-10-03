<?php

use App\Http\Controllers\Admin\MfaSetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
|
| The admin surface proper arrives in Checkpoint 7. What exists here now is
| the one page an administrator must be able to reach before anything else:
| second-factor enrolment. EnforceAdminMfa holds every other /admin route shut
| until this one has been completed.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('admin/mfa-setup', MfaSetupController::class)->name('admin.mfa-setup');
});
