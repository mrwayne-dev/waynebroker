<?php

namespace App\Domains\Wallet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's USD balance in minor units.
 *
 * This model reads. It does not write balance_cents, reserved_cents, hash_head
 * or version — the ledger service landing in Checkpoint 5 is the only path that
 * mutates them, inside a row lock, in the same transaction as the ledger entry
 * that explains the change. $fillable holds user_id alone so that a stray
 * update() or fill() with request data cannot reach the money columns, and the
 * CHECK constraints in the migration catch anything that gets past that.
 *
 * @property int $id
 * @property int $user_id
 * @property int $balance_cents
 * @property int $reserved_cents
 * @property string $hash_head
 * @property int $version
 */
class Wallet extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id'];

    /**
     * The integer casts are not cosmetic.
     *
     * MySQL returns BIGINT as a PHP string under emulated prepares. Checkpoint 3
     * lost an afternoon to exactly this: users.session_version came back as '0',
     * the middleware compared '0' === 0, and every authenticated request would
     * have been logged out in production while the suite stayed green, because
     * an in-memory factory model carried null on both sides and matched itself.
     * The same shape here would be a balance that compares false against itself
     * in a guard, so the casts are declared and a test asserts the round trip.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
            'reserved_cents' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * The only path that creates a wallet (Maveren M-3).
     *
     * firstOrCreate rather than create, so a caller never has to ask whether
     * the wallet is already there and never has a reason to write its own
     * lookup-then-insert. Laravel routes this through createOrFirst, which
     * catches the unique violation and re-reads, so two concurrent calls
     * produce one wallet and one winner rather than an exception a caller has
     * to know about. The unique index is what makes that safe; the lazy
     * creation Maveren did was unsafe precisely because nothing underneath it
     * refused the second row.
     */
    public static function createForUser(User $user): self
    {
        return static::firstOrCreate(['user_id' => $user->id]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
