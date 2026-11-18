<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;

use function Livewire\Volt\{state, title};

title('Bitwarden - Gruppe erstellen');

state([
    'name' => '',
    'accessAll' => false,
    'collections' => [],
    'users' => [],
    'loading' => false,
]);

$apiService = fn() => app(BitwardenManagementApiInterface::class);

$save = function () {
    $this->validate([
        'name' => 'required|string|max:255',
        'accessAll' => 'boolean',
        'collections' => 'array',
        'users' => 'array',
    ]);

    $this->loading = true;
    try {
        $this->apiService()->createGroup([
            'name' => $this->name,
            'accessAll' => $this->accessAll,
            'collections' => $this->collections,
            'users' => $this->users,
        ]);

        Flux::toast('Gruppe erfolgreich erstellt', variant: 'success');
        $this->dispatch('group-created');
        return redirect()->route('apps.bitwarden.admin.groups.index');
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Erstellen der Gruppe: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Neue Gruppe" subheading="Legen Sie eine Bitwarden-Gruppe an." icon="user-group">
    <x-intranet-app-bitwarden::section icon="user-group" title="Gruppe" description="Name und Zugriff der neuen Gruppe.">
        <form wire:submit="save">
            <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                <x-intranet-app-bitwarden::row label="Name" description="Anzeigename der Gruppe in Bitwarden." wide>
                    <flux:input wire:model="name" placeholder="Gruppenname" required />
                    <flux:error name="name" />
                </x-intranet-app-bitwarden::row>
                <x-intranet-app-bitwarden::row label="Zugriff" description="Zugriff auf alle Collections.">
                    <flux:checkbox wire:model="accessAll" label="Alle Collections" />
                    <flux:error name="accessAll" />
                </x-intranet-app-bitwarden::row>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-[#d0e3f9] px-5 py-4 dark:border-white/10">
                <flux:button href="{{ route('apps.bitwarden.admin.groups.index') }}" variant="ghost">
                    Abbrechen
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Gruppe erstellen</span>
                    <span wire:loading>Wird erstellt …</span>
                </flux:button>
            </div>
        </form>
    </x-intranet-app-bitwarden::section>
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
