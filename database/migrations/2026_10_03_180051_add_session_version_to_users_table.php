<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The counter that lets a password change kill every other session.
 *
 * The Maveren audit recorded the gap this closes: changing or resetting a
 * password there left every existing session alive, because nothing on the
 * server knew which sessions predated the change (audit Section 7.3, "existing
 * sessions are not invalidated on reset"). A stolen session survived the one
 * action a member takes precisely to end it.
 *
 * Bumping this column is what revokes. A session carries the version it was
 * issued under; CheckSessionVersion compares the two on every request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('session_version')->default(0)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('session_version');
        });
    }
};
