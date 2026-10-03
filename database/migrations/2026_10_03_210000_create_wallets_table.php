<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one USD balance per member (plan Section 6).
 *
 * Money is BIGINT minor units throughout. Maveren's `wallets.balance` was
 * DECIMAL(12,2) and the PHP side cast every amount to float before binding it,
 * which is finding M-19. Sub-cent inputs passed validation (`amount > 0`) and
 * were rounded by MySQL on insert, so a request for 0.001 stored 0.00 — the
 * audit marks that consequence [INFERRED]. Integers do not have the failure
 * mode: there is no amount a member can send that this column rounds.
 *
 * balance_cents is GROSS: it is the member's whole asset. reserved_cents is a
 * hold sitting inside it, not an amount beside it, so an open position's margin
 * is counted once. The invariant is SUM(ledger_entries) == balance_cents, and
 * spendable is balance_cents - reserved_cents. The debit guard that Checkpoint 5
 * builds reads the subtraction; the constraints below are what make the
 * subtraction meaningful.
 *
 * The three CHECK constraints are deliberate duplication of what the ledger
 * service will also enforce in PHP. Maveren's C-1 was an overdraft race that
 * the application layer alone could not prevent: the balance was checked
 * outside the transaction with no FOR UPDATE and no balance >= ? guard, so two
 * concurrent withdrawals both passed the check. A database that refuses the
 * write is the layer no amount of application-level carelessness can talk
 * round. MySQL has enforced CHECK since 8.0.16; the plan pins MySQL 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // Indexed but not yet unique. Uniqueness is Maveren M-3, and it
            // arrives in its own commit behind a test that fails without it.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->bigInteger('balance_cents')->default(0);
            $table->bigInteger('reserved_cents')->default(0);

            // Head of this wallet's SHA-256 ledger hash chain. Empty string
            // rather than null for a wallet with no entries yet: the chain is a
            // sequence of definite values, and a null head would make "no
            // entries" and "head lost" the same state.
            $table->char('hash_head', 64)->default('');

            // Optimistic-lock counter, incremented by the ledger service on
            // every mutation. It gives a reconciliation job a way to tell a
            // wallet that has not moved from one it failed to read.
            $table->bigInteger('version')->default(0);

            $table->timestamps();
        });

        // Raw statements because Blueprint has no check() helper. Named, so a
        // violation arrives as a constraint a reader can find in this file
        // rather than as an anonymous CONSTRAINT_1.
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_balance_cents_non_negative CHECK (balance_cents >= 0)');
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_reserved_cents_non_negative CHECK (reserved_cents >= 0)');
        DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_reserved_within_balance CHECK (reserved_cents <= balance_cents)');
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
