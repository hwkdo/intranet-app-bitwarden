<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Commands;

use Hwkdo\IntranetAppBitwarden\Services\ConfirmPendingMembersService;
use Illuminate\Console\Command;

class ConfirmPendingMembersCommand extends Command
{
    protected $signature = 'intranet-app-bitwarden:confirm-pending-members
                            {--force : Auto-Confirm-Setting ignorieren}';

    protected $description = 'Bestätigt Bitwarden-Org-Mitglieder, die Confirm brauchen (Accepted / Vaultwarden-Quirk).';

    public function handle(ConfirmPendingMembersService $service): int
    {
        $result = $service->confirmAllPending(
            respectAutoConfirmSetting: ! $this->option('force'),
        );

        if ($result['skipped']) {
            $this->warn('Auto-Confirm ist in den App-Einstellungen deaktiviert (autoConfirmEnabled=false).');

            return self::SUCCESS;
        }

        $this->info("Confirm: {$result['confirmed']}/{$result['attempted']} erfolgreich.");

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
