<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Services;

use App\Models\Gvp;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SupervisorBitwardenScope
{
    public function isSupervisor(User $user): bool
    {
        return Gvp::query()
            ->where(function ($query) use ($user): void {
                $query->where('vorgesetzter_id', $user->id)
                    ->orWhere('stellvertreter_id', $user->id);
            })
            ->exists();
    }

    /**
     * @return Collection<int, Gvp>
     */
    public function supervisedGvps(User $user): Collection
    {
        $led = Gvp::query()
            ->where(function ($query) use ($user): void {
                $query->where('vorgesetzter_id', $user->id)
                    ->orWhere('stellvertreter_id', $user->id);
            })
            ->get();

        $ids = [];

        foreach ($led as $gvp) {
            foreach ($gvp->getDescendantIds() as $id) {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return collect();
        }

        return Gvp::query()
            ->whereIn('id', array_keys($ids))
            ->orderBy('kuerzel')
            ->orderBy('nummer')
            ->get();
    }

    public function canManage(User $user, Gvp $gvp): bool
    {
        return $this->supervisedGvps($user)->contains(
            static fn (Gvp $candidate): bool => (int) $candidate->id === (int) $gvp->id,
        );
    }

    public function assertCanManage(User $user, Gvp $gvp): void
    {
        if (! $this->canManage($user, $gvp)) {
            throw new AccessDeniedHttpException('Keine Berechtigung für diese GVP.');
        }
    }
}
