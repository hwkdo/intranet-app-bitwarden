<?php

use function Livewire\Volt\{title};

title('Bitwarden - App-Info');

?>
<div>
    <x-intranet-app-bitwarden::bitwarden-layout heading="App-Info" subheading="Installierte Version und Release-Historie" icon="information-circle">
        <div class="glass-card overflow-hidden p-5">
            @livewire('intranet-app-base::app-info', ['appIdentifier' => 'bitwarden'])
        </div>
    </x-intranet-app-bitwarden::bitwarden-layout>
</div>
