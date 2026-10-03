<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retires Maveren audit finding H-1.
 *
 * Maveren had logAdminAction() in api/utilities/helpers.php and an admin_logs
 * table it wrote to. The function was never called from anywhere, and the table
 * existed in no schema file or migration. The result: balance overrides,
 * deposit approvals, withdrawal approvals, deposit-address changes, plan edits
 * and user deletions all left no record of who did them. This table is the
 * other half of that lesson — it exists, and a middleware writes to it without
 * anyone having to remember.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_events', function (Blueprint $table) {
            $table->id();

            // Nullable on purpose. An unauthenticated write attempt against an
            // /admin route is exactly the kind of thing an audit log exists to
            // notice, and a null actor is more honest than no row at all.
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action');

            // Morph-style pointer, kept loose: the subject may be a wallet, a
            // user, a ledger entry or something that does not exist yet.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Domain callers pass these; the catch-all middleware cannot know
            // them and leaves them null.
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->text('reason')->nullable();

            // 45 characters holds an IPv6 address with an IPv4 tail. MySQL has
            // no INET type — that is Postgres — so this is a string.
            $table->string('ip', 45)->nullable();
            $table->string('session_id')->nullable();

            // created_at only. An audit row that can be updated is not an audit
            // row, so there is no updated_at to tempt anyone.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['admin_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_events');
    }
};
