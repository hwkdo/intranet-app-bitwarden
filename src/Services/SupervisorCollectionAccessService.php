<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\Gvp;
use App\Models\User;
use Hwkdo\IntranetAppBitwarden\Models\GvpBitwardenExclusion;
use Hwkdo\IntranetAppBitwarden\Models\GvpBitwardenPref;
use Hwkdo\IntranetAppBitwarden\Support\BitwardenMemberEligibility;
use Illuminate\Support\Collection;
use RuntimeException;

class SupervisorCollectionAccessService
{
    public function __construct(
        protected SupervisorBitwardenScope $scope,
        protected GvpBitwardenMembershipService $membershipService,
    ) {}

    public function setIncludeAzubis(User $actor, Gvp $gvp, bool $include): GvpBitwardenPref
    {
        $this->scope->assertCanManage($actor, $gvp);

        $pref = GvpBitwardenPref::forGvp($gvp);
        $pref->include_azubis = $include;
        $pref->save();

        $this->membershipService->syncGroupMembers($gvp->fresh() ?? $gvp);

        return $pref;
    }

    public function setIncludePraktikanten(User $actor, Gvp $gvp, bool $include): GvpBitwardenPref
    {
        $this->scope->assertCanManage($actor, $gvp);

        $pref = GvpBitwardenPref::forGvp($gvp);
        $pref->include_praktikanten = $include;
        $pref->save();

        $this->membershipService->syncGroupMembers($gvp->fresh() ?? $gvp);

        return $pref;
    }

    public function revoke(User $actor, Gvp $gvp, User $member): void
    {
        $this->scope->assertCanManage($actor, $gvp);

        if ((int) $actor->id === (int) $member->id) {
            throw new RuntimeException('Sie können sich nicht selbst den Zugriff entziehen.');
        }

        GvpBitwardenExclusion::query()->firstOrCreate([
            'gvp_id' => $gvp->id,
            'user_id' => $member->id,
        ]);

        $this->membershipService->syncGroupMembers($gvp->fresh() ?? $gvp);
    }

    public function restore(User $actor, Gvp $gvp, User $member): void
    {
        $this->scope->assertCanManage($actor, $gvp);

        GvpBitwardenExclusion::query()
            ->where('gvp_id', $gvp->id)
            ->where('user_id', $member->id)
            ->delete();

        $this->membershipService->syncGroupMembers($gvp->fresh() ?? $gvp);
    }

    /**
     * @return Collection<int, User>
     */
    public function eligibleMembers(Gvp $gvp): Collection
    {
        $members = BitwardenMemberEligibility::filterUsersForGvp(
            $gvp->getAllMembersForBitwarden(),
            $gvp,
        );

        return collect($members)->unique('id')->values();
    }

    /**
     * Mitglieder aller Gruppen, die an der Gesamt-Collection der Abteilung hängen.
     *
     * @return Collection<int, User>
     */
    public function gesamtEligibleMembers(Gvp $abteilung): Collection
    {
        $abteilung->loadMissing('childGvps');

        $units = collect([$abteilung])->concat($abteilung->childGvps);
        $byId = [];

        foreach ($units as $unit) {
            if (! $unit->hasBitwardenGroup()) {
                continue;
            }

            foreach ($this->eligibleMembers($unit) as $member) {
                $byId[(int) $member->id] = $member;
            }
        }

        return collect($byId)
            ->sortBy([
                ['nachname', 'asc'],
                ['vorname', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function excludedMembers(Gvp $gvp): Collection
    {
        $userIds = GvpBitwardenExclusion::query()
            ->where('gvp_id', $gvp->id)
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('nachname')
            ->orderBy('vorname')
            ->get();
    }
}
