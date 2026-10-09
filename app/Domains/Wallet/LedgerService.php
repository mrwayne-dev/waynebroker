<?php

declare(strict_types=1);

namespace App\Domains\Wallet;

use App\Domains\Wallet\Exceptions\InsufficientFundsException;
use App\Domains\Wallet\Models\LedgerEntry;
use App\Domains\Wallet\Models\Wallet;
use App\Support\Money\Cents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The one path that moves money (plan Section 6; Rule 4 in CLAUDE.md).
 *
 * No controller, job or command writes wallets.balance_cents. They call this.
 * Every mutation is a row lock, a debit guard that runs inside the lock, a
 * ledger entry that explains the change, the hash chain extended by one, and
 * the wallet's cache (balance_cents, hash_head, version) advanced — all in a
 * single transaction, so the balance and the ledger can never disagree.
 *
 * This retires Maveren C-1. There, api/backend/wallet.php read the balance
 * before BEGIN, with no FOR UPDATE and no "AND balance >= ?" on the debit
 * UPDATE, so two concurrent withdrawals both passed the check and both debited.
 * Here the balance read IS the lock (lockForUpdate), so a second debit on the
 * same wallet waits for the first to commit and then reads the balance the
 * first one left — and the guard refuses it. The ConcurrentWithdrawalRaceTest
 * is the proof; this class is what turns it green.
 */
final class LedgerService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function credit(
        Wallet $wallet,
        Cents $amount,
        string $kind,
        ?Model $reference = null,
        ?array $metadata = null,
        ?string $idempotencyKey = null,
    ): LedgerEntry {
        return $this->append('credit', $wallet, $amount, $kind, $reference, $metadata, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function debit(
        Wallet $wallet,
        Cents $amount,
        string $kind,
        ?Model $reference = null,
        ?array $metadata = null,
        ?string $idempotencyKey = null,
    ): LedgerEntry {
        return $this->append('debit', $wallet, $amount, $kind, $reference, $metadata, $idempotencyKey);
    }

    /**
     * @param  'credit'|'debit'  $direction
     * @param  array<string, mixed>|null  $metadata
     */
    private function append(
        string $direction,
        Wallet $wallet,
        Cents $amount,
        string $kind,
        ?Model $reference,
        ?array $metadata,
        ?string $idempotencyKey,
    ): LedgerEntry {
        // Idempotency, fast path. A webhook redelivered after its entry
        // committed (C-3's shape) presents the same key; we return the existing
        // entry without opening a transaction or taking a lock. The slow path
        // below closes the window where two deliveries arrive before either has
        // committed.
        if ($idempotencyKey !== null) {
            $existing = LedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $referenceType = $reference?->getMorphClass();
        $referenceId = $reference?->getKey();

        return DB::transaction(function () use (
            $direction,
            $wallet,
            $amount,
            $kind,
            $referenceType,
            $referenceId,
            $metadata,
            $idempotencyKey,
        ): LedgerEntry {
            // The lock. Every other writer on this wallet now waits here, so the
            // balance this row reads is one no concurrent debit can be partway
            // through changing. This single line is the difference between C-1
            // and its fix.
            $locked = DB::table('wallets')
                ->where('id', $wallet->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                // Every member has a wallet from registration (Maveren M-3), so
                // this is unreachable in normal flow. It is here because a money
                // mutation against a wallet that does not exist must be a loud
                // failure, never a silent skip of the kind H-4 made routine.
                throw new \RuntimeException("Wallet [{$wallet->id}] not found while locking it for a ledger write.");
            }

            // The guard, inside the lock. Gross balance model (R6): the whole
            // asset is balance_cents, a hold sits inside it as reserved_cents,
            // and spendable is the difference. A debit may only draw on what is
            // not already reserved.
            if ($direction === 'debit') {
                $available = (int) $locked->balance_cents - (int) $locked->reserved_cents;

                if ($available < $amount->toInt()) {
                    // Thrown before any INSERT. The transaction rolls back with
                    // no ledger row written and the wallet untouched. This — not
                    // the CHECK constraint — is what a caller sees when a debit
                    // cannot be covered; the CHECK is only the backstop for a
                    // write that never goes through this method.
                    throw InsufficientFundsException::forDebit($available, $amount->toInt());
                }
            }

            $createdAt = now();

            // The chain link. Each field that defines what the entry means is
            // folded in, so a later edit to any of them breaks this hash and
            // every hash after it. previous_hash is the wallet's current head;
            // the new hash becomes the head once the wallet row is updated below.
            $hashInput = $locked->hash_head
                .'|'.$direction
                .'|'.$amount->toInt()
                .'|'.$kind
                .'|'.($referenceType ?? '')
                .'|'.($referenceId ?? '')
                .'|'.$wallet->id
                .'|'.$createdAt->timestamp;

            $hash = hash('sha256', $hashInput);

            try {
                $entry = LedgerEntry::query()->create([
                    'wallet_id' => $wallet->id,
                    'direction' => $direction,
                    'amount_cents' => $amount->toInt(),
                    'kind' => $kind,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'metadata' => $metadata,
                    'previous_hash' => $locked->hash_head,
                    'hash' => $hash,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => $createdAt,
                ]);
            } catch (QueryException $e) {
                // Idempotency, slow path. Two deliveries of the same key both
                // got past the fast-path SELECT before either committed; the
                // UNIQUE index on idempotency_key lets exactly one INSERT land
                // and rejects the other here. The loser reads and returns the
                // winner's entry rather than bubbling a duplicate-key error to a
                // caller that only asked to record the movement once. A
                // duplicate-key is a statement error in MySQL, not a transaction
                // abort, so the SELECT below runs on the same still-open
                // transaction.
                if ($idempotencyKey !== null && $this->isDuplicateIdempotencyKey($e)) {
                    // A LOCKING read, not a plain one. This transaction's
                    // consistent-read snapshot was taken before the winner
                    // committed (that is exactly why the fast-path SELECT above
                    // missed and we are here), and under REPEATABLE READ a plain
                    // SELECT would still see that stale snapshot and find
                    // nothing. lockForUpdate forces a current read of the latest
                    // committed row, which is the winner we just collided with.
                    return LedgerEntry::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->firstOrFail();
                }

                throw $e;
            }

            $delta = $direction === 'credit' ? $amount->toInt() : -$amount->toInt();

            // balance_cents moves as a column-relative delta (balance_cents =
            // balance_cents + $delta) rather than a value read in PHP and written
            // back, so even if a lock were somehow missed the arithmetic is the
            // database's and not a stale read's. incrementEach does exactly that
            // for balance_cents and version in one atomic UPDATE, and sets
            // hash_head to this entry's hash — the chain head advances in the
            // same statement. A negative $delta is a debit; incrementEach takes
            // it unchanged.
            DB::table('wallets')
                ->where('id', $wallet->id)
                ->incrementEach(
                    ['balance_cents' => $delta, 'version' => 1],
                    ['hash_head' => $hash],
                );

            return $entry;
        });
    }

    /**
     * Whether a write failed specifically on the idempotency_key unique index.
     *
     * Checked by the constraint name the migration gave the index
     * (ledger_entries_idempotency_key_unique, Laravel's default) so that a
     * different unique violation — one we did not plan for — is not silently
     * swallowed as if it were a duplicate webhook.
     */
    private function isDuplicateIdempotencyKey(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'idempotency_key');
    }
}
