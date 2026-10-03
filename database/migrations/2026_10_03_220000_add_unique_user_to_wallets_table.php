<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One wallet per member, enforced where it cannot be raced (Maveren M-3).
 *
 * A separate migration rather than an edit to the create, because the previous
 * commit landed a test that fails without this and the failure is the record
 * that the constraint is doing work. Environments already carrying wallets get
 * the constraint by upgrade, which is also the realistic case: Maveren's two
 * duplicated accounts would have to be reconciled before this would apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            // The foreign key needs an index on the column, and in MySQL the
            // unique index may be the only one satisfying it. Adding the plain
            // index first keeps the key valid through the drop.
            $table->index('user_id', 'wallets_user_id_index');
            $table->dropUnique(['user_id']);
        });
    }
};
