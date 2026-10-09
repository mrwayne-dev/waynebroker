<?php

namespace App\Domains\Wallet\Models;

use App\Domains\Wallet\Exceptions\LedgerEntryIsImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One immutable line in a wallet's ledger.
 *
 * The write path is LedgerService and nothing else; this model is the read
 * side plus two guards that make a write through it impossible.
 *
 * No $fillable allowlist. That reads as the opposite of the Wallet model's
 * single-column $fillable, and it is deliberate: a ledger entry is never built
 * from request data — LedgerService constructs the whole row from values it
 * computed itself (the hash, the previous_hash, the direction) — so there is no
 * untrusted field to guard against, and the thing actually worth preventing is
 * not mass assignment but mutation of a row already written. That is what the
 * overridden update()/delete() and the database triggers do. $guarded = []
 * keeps create() working for the one trusted caller; the immutability guards do
 * the real work.
 *
 * @property int $id
 * @property int $wallet_id
 * @property string $direction
 * @property int $amount_cents
 * @property string $kind
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property array<string, mixed>|null $metadata
 * @property string $previous_hash
 * @property string $hash
 * @property string|null $idempotency_key
 * @property Carbon $created_at
 */
class LedgerEntry extends Model
{
    /** @var array<int, string> */
    protected $guarded = [];

    /**
     * created_at exists; updated_at does not. Telling Eloquent there is no
     * UPDATED_AT column stops it writing one on create (there is no column to
     * write), while still letting it manage created_at. $timestamps stays true
     * for exactly that reason — the plan's "created_at only" is a column fact,
     * not a request to hand-manage timestamps.
     */
    public const UPDATED_AT = null;

    /**
     * Refuse an update before it reaches the database.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw LedgerEntryIsImmutableException::forUpdate();
    }

    public function delete(): ?bool
    {
        throw LedgerEntryIsImmutableException::forDelete();
    }

    /**
     * Same discipline as the Wallet casts, and for the same Checkpoint 3
     * reason: amount_cents is BIGINT and must never arrive as a string into a
     * comparison or a sum. metadata is JSON the service wrote as an array.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
