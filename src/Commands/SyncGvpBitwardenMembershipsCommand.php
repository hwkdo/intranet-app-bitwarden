<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Commands;

use App\Models\Gvp;
use Hwkdo\IntranetAppBitwarden\Services\GvpBitwardenCollectionAccessService;
use Hwkdo\IntranetAppBitwarden\Services\GvpBitwardenMembershipService;
use Hwkdo\IntranetAppBitwarden\Support\BitwardenSyncGuard;
use Illuminate\Console\Command;

class SyncGvpBitwardenMembershipsCommand extends Command
{
    protected $signature = 'intranet-app-bitwarden:sync-gvp-memberships';

    protected $description = 'Synchronisiert Bitwarden-Gruppenmitglieder und Collection-ACLs aller GVPs';

    public function handle(
        GvpBitwardenMembershipService $membershipService,
        GvpBitwardenCollectionAccessService $collectionAccessService,
    ): int {
        if (BitwardenSyncGuard::isPaused()) {
            $this->warn('Bitwarden-Sync ist pausiert (Full Reset läuft) — abgebrochen.');

            return self::SUCCESS;
        }

        if (! class_exists(Gvp::class)) {
            $this->warn('Gvp-Modell nicht verfügbar.');

            return self::FAILURE;
        }

        $gvpsWithGroup = Gvp::query()
            ->whereNotNull('bitwarden_group_id')
            ->where('bitwarden_group_id', '!=', '')
            ->get();

        foreach ($gvpsWithGroup as $gvp) {
            $this->line("Sync Mitglieder GVP #{$gvp->id} ({$gvp->name})…");
            $membershipService->syncGroupMembers($gvp);
        }

        $gvpsWithCollections = Gvp::query()
            ->with(['childGvps', 'vorgesetzter', 'stellvertreter'])
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('bitwarden_collection_id')
                        ->where('bitwarden_collection_id', '!=', '');
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('bitwarden_gesamt_collection_id')
                        ->where('bitwarden_gesamt_collection_id', '!=', '');
                });
            })
            ->get();

        foreach ($gvpsWithCollections as $gvp) {
            if ($gvp->hasBitwardenCollection()) {
                $this->line("Sync Direct-Collection-ACL GVP #{$gvp->id}…");
                $collectionAccessService->syncDirectCollectionAccess($gvp);
            }

            if ($gvp->hasBitwardenGesamtCollection()) {
                $this->line("Sync Gesamt-Collection-ACL GVP #{$gvp->id}…");
                $collectionAccessService->syncGesamtCollectionAccess($gvp);
            }
        }

        $this->info("Fertig: {$gvpsWithGroup->count()} Gruppen- und {$gvpsWithCollections->count()} Collection-Sync(s).");

        return self::SUCCESS;
    }
}
