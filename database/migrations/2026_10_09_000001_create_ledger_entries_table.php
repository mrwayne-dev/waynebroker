<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The append-only ledger (plan Section 6, principle 3).
 *
 * Every movement of money is a row here, and the row is never changed once
 * written. wallets.balance_cents is a materialised cache of this table; the
 * invariant the reconciliation job checks is
 * SUM(credit amount_cents) - SUM(debit amount_cents) == balance_cents (R6,
 * gross model). That only holds if the history cannot be rewritten, which is
 * what this migration enforces at the one layer the application cannot talk
 * round.
 *
 * Maveren had no ledger. wallets.balance was mutated directly (H-2: an absolute
 * admin override with no offsetting record), so SUM(transactions) could not be
 * reconciled to the balance and money could be created or removed invisibly.
 * The fix is not "log the writes" — it is to make the writes BE the balance and
 * to refuse any edit to them.
 *
 * Two defences sit on top of each other, deliberately:
 *   1. A hash chain (previous_hash -> hash, per wallet) makes a tamper
 *      detectable even to someone with UPDATE rights, because every later row's
 *      hash depends on this one.
 *   2. BEFORE UPDATE / BEFORE DELETE triggers refuse the edit outright, so the
 *      tamper cannot happen in the first place through the application's own
 *      connection. The trigger is the guarantee; the chain is how a breach of
 *      the guarantee is found after the fact.
 */
return new class extends Migration
{
    /**
     * The controlled vocabulary for ledger_entries.kind.
     *
     * Kept as a DB CHECK rather than an application enum alone, for the same
     * reason the wallet constraints are: the ledger is the system of record for
     * money, and a kind nobody planned for is a write that should be refused by
     * the database, not merely frowned on in PHP. New kinds arrive by migration,
     * which is the right amount of friction for the money vocabulary — the same
     * schema-time discipline R6 chose for role names.
     *
     * @var list<string>
     */
    private const KINDS = [
        'deposit',
        'withdrawal',
        'roi_payout',
        'principal_release',
        'adjustment',
        'agent_pnl',
        'bonus',
        'correction',
        'migration_open',
        'migration_history',
    ];

    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();

            // A real ENUM: there are exactly two directions and there will only
            // ever be two. amount_cents carries the magnitude; direction carries
            // the sign, so the amount is unsigned and CHECK amount_cents > 0
            // means a zero-value entry — which explains nothing and reconciles
            // to nothing — cannot be written either.
            $table->enum('direction', ['credit', 'debit']);
            $table->bigInteger('amount_cents');

            // VARCHAR rather than a native ENUM so the vocabulary lives in a
            // CHECK constraint this file names and a reader can grep, and so
            // adding a kind is an ALTER of a constraint rather than an ENUM
            // column rewrite.
            $table->string('kind', 32);

            // Polymorphic pointer at whatever caused the entry (a deposit
            // invoice, a payout, an admin adjustment). Nullable because an
            // opening migration entry references nothing.
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->json('metadata')->nullable();

            // The chain. CHAR(64) because a SHA-256 hex digest is always 64
            // characters; previous_hash is '' for the first entry on a wallet,
            // mirroring wallets.hash_head's empty-string start, so "no previous
            // entry" and "previous hash lost" are not the same value.
            $table->char('previous_hash', 64);
            $table->char('hash', 64);

            // The idempotency key that retires the other half of the IPN race
            // (C-3): a provider that delivers the same webhook twice presents
            // the same key twice, and the UNIQUE index turns the second credit
            // into a no-op the service can detect. Nullable because most entries
            // (an admin adjustment, an ROI payout) have no external key.
            $table->string('idempotency_key')->nullable()->unique();

            // created_at only. The plan is explicit: no updated_at (the row is
            // never updated) and no deleted_at (the row is never deleted). A
            // correction is a new offsetting entry, not an edit or a soft delete.
            $table->timestamp('created_at')->nullable();

            // The chain is walked per wallet, newest to oldest, so this is the
            // access path the integrity check and any audit read actually use.
            $table->index(['wallet_id', 'id']);
        });

        $kinds = implode(',', array_map(static fn (string $k): string => "'{$k}'", self::KINDS));

        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_cents_positive CHECK (amount_cents > 0)');
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_kind_known CHECK (kind IN ({$kinds}))");

        // SIGNAL SQLSTATE '45000' is MySQL's "unhandled user-defined exception";
        // it surfaces in PHP as a QueryException, which is what the append-only
        // tests assert. The message is the same the Wallet audit comments
        // describe: the ledger is the one place carelessness cannot reach.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER ledger_entries_block_update
            BEFORE UPDATE ON ledger_entries
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries is append-only';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER ledger_entries_block_delete
            BEFORE DELETE ON ledger_entries
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries is append-only';
        SQL);
    }

    public function down(): void
    {
        // Triggers first: a trigger on a table dropped out from under it is an
        // orphan on some MySQL versions, and the rollback of a RefreshDatabase
        // run must leave nothing behind for the next migrate.
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_update');
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_delete');

        Schema::dropIfExists('ledger_entries');
    }
};
