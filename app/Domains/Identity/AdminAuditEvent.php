<?php

namespace App\Domains\Identity;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recorded administrative action.
 *
 * Read-only by construction: the table has no updated_at, the model has no
 * fillable, and nothing in the application is expected to amend a row once
 * written. Corrections take the same route the ledger does — a new row saying
 * what was corrected, never an edit that erases what was first recorded.
 *
 * @property int $id
 * @property int|null $admin_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $reason
 * @property string|null $ip
 * @property string|null $session_id
 * @property Carbon $created_at
 */
class AdminAuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
