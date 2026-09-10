<?php

use function Livewire\Volt\{title};

title('Bitwarden - App-Info');

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="App-Info" subheading="Installierte Version und Release-Historie">
    @livewire('intranet-app-base::app-info', ['appIdentifier' => 'bitwarden'])
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
