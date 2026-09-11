<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\Gvp;
use App\Models\User;
use Hwkdo\IntranetAppBitwarden\Data\MeineSammlungCard;
use Illuminate\Support\Collection;

class MemberBitwardenScope
{
    public function __construct(
        protected SupervisorBitwardenScope $supervisorScope,
    ) {}

    /**
     * GVPs, die der User in „Meine Sammlungen“ sehen darf (eigene + Gesamt-Vorfahren + Supervisor-Scope).
     *
     * @return Collection<int, Gvp>
     */
    public function visibleGvps(User $user): Collection
    {
        $byId = [];

        foreach ($this->memberGvps($user) as $gvp) {
            $byId[(int) $gvp->id] = $gvp;
        }

        if ($this->supervisorScope->isSupervisor($user)) {
            foreach ($this->supervisorScope->supervisedGvps($user) as $gvp) {
                $byId[(int) $gvp->id] = $gvp;
            }
        }

        return collect($byId)
            ->sortBy([
                ['kuerzel', 'asc'],
                ['nummer', 'asc'],
            ])
            ->values();
    }

    /**
     * Karten für die UI: Direkt-Collection und Gesamt-Collection getrennt.
     *
     * @return Collection<int, MeineSammlungCard>
     */
    public function visibleCards(User $user): Collection
    {
        $cards = [];

        foreach ($this->visibleGvps($user) as $gvp) {
            $seesAsGesamtOnly = $this->seesPrimarilyAsGesamt($user, $gvp);
            $hasDirect = $gvp->hasBitwardenGroup() || $gvp->hasBitwardenCollection();
            $hasGesamt = $gvp->hasBitwardenGesamtCollection();

            if ($hasDirect && ! $seesAsGesamtOnly) {
                $cards[] = new MeineSammlungCard($gvp, isGesamt: false);
            }

            if ($hasGesamt && $this->shouldShowGesamtCard($user, $gvp, $seesAsGesamtOnly)) {
                $cards[] = new MeineSammlungCard($gvp, isGesamt: true);
            }
        }

        return collect($cards)
            ->sortBy([
                fn (MeineSammlungCard $card): string => (string) $card->gvp->kuerzel,
                fn (MeineSammlungCard $card): string => (string) $card->gvp->nummer,
                fn (MeineSammlungCard $card): int => $card->isGesamt ? 1 : 0,
            ])
            ->values();
    }

    /**
     * Eigene GVP plus Abteilungen mit Gesamt-Collection, auf die der User über seine Gruppe Zugriff hat.
     *
     * @return Collection<int, Gvp>
     */
    public function memberGvps(User $user): Collection
    {
        if ($user->gvp_id === null) {
            return collect();
        }

        $gvp = Gvp::query()->with('parent')->find($user->gvp_id);

        if ($gvp === null) {
            return collect();
        }

        $byId = [];

        if ($gvp->hasBitwardenGroup() || $gvp->hasBitwardenCollection() || $gvp->hasBitwardenGesamtCollection()) {
            $byId[(int) $gvp->id] = $gvp;
        }

        if ($gvp->hasBitwardenGroup()) {
            $ancestor = $gvp->parent;

            while ($ancestor !== null) {
                if ($ancestor->hasBitwardenGesamtCollection()) {
                    $byId[(int) $ancestor->id] = $ancestor;
                }

                $ancestor->loadMissing('parent');
                $ancestor = $ancestor->parent;
            }
        }

        return collect($byId)->values();
    }

    /**
     * true, wenn die GVP nur wegen Gesamt-Zugriff sichtbar ist (nicht Stamm-GVP, kein Supervisor).
     */
    public function seesPrimarilyAsGesamt(User $user, Gvp $gvp): bool
    {
        if (! $gvp->hasBitwardenGesamtCollection()) {
            return false;
        }

        if ((int) $user->gvp_id === (int) $gvp->id) {
            return false;
        }

        if ($this->supervisorScope->canManage($user, $gvp)) {
            return false;
        }

        return $this->memberGvps($user)->contains(
            static fn (Gvp $candidate): bool => (int) $candidate->id === (int) $gvp->id,
        );
    }

    protected function shouldShowGesamtCard(User $user, Gvp $gvp, bool $seesAsGesamtOnly): bool
    {
        if ($seesAsGesamtOnly) {
            return true;
        }

        if ((int) $user->gvp_id === (int) $gvp->id) {
            return true;
        }

        return $this->supervisorScope->canManage($user, $gvp);
    }
}
