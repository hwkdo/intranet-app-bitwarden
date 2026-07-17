<?php

namespace Hwkdo\IntranetAppBitwarden\Commands;

use App\Models\Gvp;
use Hwkdo\IntranetAppBitwarden\Services\GvpBitwardenMembershipService;
use Illuminate\Console\Command;

class SyncGvpBitwardenMembershipsCommand extends Command
{
    protected $signature = 'intranet-app-bitwarden:sync-gvp-memberships';

    protected $description = 'Synchronisiert Bitwarden-Gruppenmitglieder aller GVPs und bestätigt Accepted-Mitglieder.';

    public function handle(GvpBitwardenMembershipService $service): int
    {
        if (! class_exists(Gvp::class)) {
            $this->warn('Gvp-Modell nicht verfügbar.');

            return self::FAILURE;
        }

        $gvps = Gvp::query()
            ->whereNotNull('bitwarden_group_id')
            ->where('bitwarden_group_id', '!=', '')
            ->get();

        if ($gvps->isEmpty()) {
            $this->info('Keine GVPs mit Bitwarden-Gruppe gefunden.');

            return self::SUCCESS;
        }

        foreach ($gvps as $gvp) {
            $this->line("Sync GVP #{$gvp->id} ({$gvp->name})…");
            $service->syncGroupMembers($gvp);
        }

        $this->info("Fertig: {$gvps->count()} GVP(s) synchronisiert.");

        return self::SUCCESS;
    }
}
