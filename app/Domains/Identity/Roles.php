<?php

namespace App\Domains\Identity;

/**
 * The complete role vocabulary, as named in plan Sections 5 and 6.
 *
 * These exist as constants rather than as loose strings because of Maveren
 * audit finding H-8. There, `admins.role` was ENUM('super_admin','manager',
 * 'support') while two endpoints gated on the string 'support_admin' — a value
 * the column could never hold. The gate failed closed, so nobody noticed; KYC
 * review was quietly unreachable for every role except super_admin, and
 * withdrawals queued behind a screen only one person could open. A typo in a
 * string literal cost the platform a working review queue.
 *
 * Nothing in this application may name a role by literal. RoleSeeder reads
 * these to populate the roles table, and RolesReferenceRealRoleTest reads them
 * to prove no gate has drifted away from the vocabulary.
 */
final class Roles
{
    /** Everything, including role assignment and deposit addresses. */
    public const string SUPER_ADMIN = 'super_admin';

    /** Moves money: deposits, withdrawals, ledger adjustments. */
    public const string FINANCE_ADMIN = 'finance_admin';

    /** Reads member records and the review queues; moves no money. */
    public const string SUPPORT_ADMIN = 'support_admin';

    /** Announcements and marketing content. */
    public const string CONTENT_ADMIN = 'content_admin';

    /** A member of the platform. The default for anyone registering. */
    public const string MEMBER = 'member';

    /**
     * Every role, in descending order of privilege.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::SUPER_ADMIN,
            self::FINANCE_ADMIN,
            self::SUPPORT_ADMIN,
            self::CONTENT_ADMIN,
            self::MEMBER,
        ];
    }

    /**
     * The roles that make someone staff. Everything else is a member.
     *
     * @return list<string>
     */
    public static function admin(): array
    {
        return [
            self::SUPER_ADMIN,
            self::FINANCE_ADMIN,
            self::SUPPORT_ADMIN,
            self::CONTENT_ADMIN,
        ];
    }
}
