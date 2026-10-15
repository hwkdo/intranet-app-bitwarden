<?php

use function Livewire\Volt\title;

title('Hintergrund');

?>

<div>
    <x-intranet-app-bitwarden::bitwarden-layout heading="Hintergrund" subheading="Geplante Tasks, Jobs, Mails und Benachrichtigungen">
        @livewire('intranet-app-base::background-activities', ['appIdentifier' => 'bitwarden'])
    </x-intranet-app-bitwarden::bitwarden-layout>
</div>
