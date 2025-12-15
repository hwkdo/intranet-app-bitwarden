<?php

use function Livewire\Volt\{title};

title('Bitwarden - Meine Einstellungen');

?>

<x-intranet-app-bitwarden::bitwarden-layout heading="Meine Einstellungen" subheading="Persönliche Einstellungen für die Bitwarden App">
    @livewire('intranet-app-base::user-settings', ['appIdentifier' => 'bitwarden'])
</x-intranet-app-bitwarden::bitwarden-layout>
