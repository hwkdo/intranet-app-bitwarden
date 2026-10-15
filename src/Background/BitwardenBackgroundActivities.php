<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Background;

use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityFrequency;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;
use Hwkdo\IntranetAppBitwarden\IntranetAppBitwarden;
use Hwkdo\IntranetAppBitwarden\Jobs\RunBitwardenFullResetJob;

final class BitwardenBackgroundActivities
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function definitions(): array
    {
        $id = IntranetAppBitwarden::identifier();
        $name = IntranetAppBitwarden::app_name();
        $all = BackgroundActivityCadence::cases();

        return [
            BackgroundActivityDefinition::scheduled(
                key: 'bitwarden.sync-gvp-memberships',
                label: 'GVP-Mitgliedschaften synchronisieren',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-bitwarden:sync-gvp-memberships',
                frequency: BackgroundActivityFrequency::everyMinutes(15),
                description: 'Synchronisiert Bitwarden-GVP-Gruppen und Collection-ACLs.',
                allowedCadences: $all,
                minEvery: 5,
                maxEvery: 60,
                withoutOverlappingMinutes: 1440,
                sort: 10,
            ),
            BackgroundActivityDefinition::scheduled(
                key: 'bitwarden.confirm-pending-members',
                label: 'Ausstehende Mitglieder bestätigen',
                appIdentifier: $id,
                appName: $name,
                command: 'intranet-app-bitwarden:confirm-pending-members',
                frequency: BackgroundActivityFrequency::everyMinutes(15),
                description: 'Bestätigt ausstehende Organisationsmitglieder.',
                allowedCadences: $all,
                minEvery: 5,
                maxEvery: 60,
                withoutOverlappingMinutes: 1440,
                sort: 20,
            ),
            BackgroundActivityDefinition::queued(key: 'bitwarden.full-reset', label: 'Vollständiger Reset', appIdentifier: $id, appName: $name, job: RunBitwardenFullResetJob::class, description: 'Setzt Bitwarden-Mitgliedschaften und Collections neu auf.', sort: 30),
        ];
    }
}
