<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Support;

use App\Models\Gvp;
use Hwkdo\IntranetAppBitwarden\Models\GvpBitwardenExclusion;
use Hwkdo\IntranetAppBitwarden\Models\GvpBitwardenPref;

final class BitwardenMemberEligibility
{
    public static function isPraktikant(object $user): bool
    {
        return (bool) ($user->praktikant ?? false);
    }

    public static function isAzubi(object $user): bool
    {
        return (bool) ($user->azubi ?? false);
    }

    public static function allowsUserForGvp(object $user, Gvp $gvp): bool
    {
        $userId = (int) ($user->id ?? 0);

        if ($userId > 0 && GvpBitwardenExclusion::isExcluded((int) $gvp->id, $userId)) {
            return false;
        }

        $pref = GvpBitwardenPref::forGvp($gvp);

        if (! $pref->include_azubis && self::isAzubi($user)) {
            return false;
        }

        if (! $pref->include_praktikanten && self::isPraktikant($user)) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<object>  $users
     * @return list<object>
     */
    public static function filterUsersForGvp(array $users, Gvp $gvp): array
    {
        return array_values(array_filter(
            $users,
            static fn (object $user): bool => self::allowsUserForGvp($user, $gvp),
        ));
    }

    /**
     * Skip-Grund für Workflow-Invite, oder null wenn erlaubt.
     *
     * @return 'excluded'|'azubi'|'praktikant'|null
     */
    public static function inviteSkipReason(object $user, ?Gvp $gvp): ?string
    {
        if ($gvp === null) {
            return null;
        }

        $userId = (int) ($user->id ?? 0);

        if ($userId > 0 && GvpBitwardenExclusion::isExcluded((int) $gvp->id, $userId)) {
            return 'excluded';
        }

        $pref = GvpBitwardenPref::forGvp($gvp);

        if (! $pref->include_azubis && self::isAzubi($user)) {
            return 'azubi';
        }

        if (! $pref->include_praktikanten && self::isPraktikant($user)) {
            return 'praktikant';
        }

        return null;
    }
}
