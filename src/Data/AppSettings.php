<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Data;

use Hwkdo\BitwardenLaravel\Enums\ManagementApiDriver;
use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseAppSettings;

class AppSettings extends BaseAppSettings
{
    public function __construct(
        #[Description('Aktiviert die Beispiel-Funktionalität')]
        public bool $enableExampleFeature = true,

        #[Description('Maximale Anzahl von Elementen pro Seite')]
        public int $maxItemsPerPage = 25,

        #[Description('Standard-Theme für die App')]
        public string $defaultTheme = 'light',

        #[Description('Liste der erlaubten Bereiche')]
        public array $allowedAreas = ['public', 'private'],

        #[Description('Management-API-Treiber: Public API (Fork) oder Native (Stock-Vaultwarden)')]
        public ManagementApiDriver $managementApiDriver = ManagementApiDriver::Public,

        #[Description('Bitwarden Organization ID (optional; leer = aus Organization-API-Client-ID ableiten)')]
        public string $bitwardenOrganizationId = '',

        #[Description('Vaultwarden Admin-Token (für Native-Treiber, z. B. Mitglieder löschen)')]
        public string $bitwardenAdminToken = '',

        #[Description('Native-Treiber: User-API-Key Client-ID (ohne organization.-Präfix; aus Vaultwarden-Konto eines Org-Owners)')]
        public string $bitwardenNativeApiClientId = '',

        #[Description('Native-Treiber: User-API-Key Client-Secret')]
        public string $bitwardenNativeApiClientSecret = '',
    ) {}
}
