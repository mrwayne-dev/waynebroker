<?php

declare(strict_types=1);

namespace App\Domains\Wallet\Exceptions;

use LogicException;

/**
 * An attempt was made to update or delete a ledger entry through Eloquent.
 *
 * A LogicException, not a QueryException, because this one never reaches the
 * database: the model refuses before the statement is built. It is the inner of
 * two guards. The database triggers (ledger_entries_block_update /
 * _block_delete) are the real guarantee and would also refuse a raw query
 * builder write; this one turns the common mistake — calling ->update() or
 * ->delete() on a loaded model — into an error that names the rule at the line
 * that broke it, rather than a SQLSTATE 45000 several frames down.
 *
 * A correction is a new offsetting entry, never an edit. If code needs to
 * "change" a ledger entry, the code is wrong, and this is where it is told so.
 */
final class LedgerEntryIsImmutableException extends LogicException
{
    public static function forUpdate(): self
    {
        return new self('ledger_entries is append-only: an entry cannot be updated. Record a new offsetting entry (kind: correction) instead.');
    }

    public static function forDelete(): self
    {
        return new self('ledger_entries is append-only: an entry cannot be deleted. Financial history is never removed (plan Section 6, principle 6).');
    }
}
