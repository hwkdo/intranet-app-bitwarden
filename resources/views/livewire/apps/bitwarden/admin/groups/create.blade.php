<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;

use function Livewire\Volt\{state, title};

title('Bitwarden - Gruppe erstellen');

state([
    'name' => '',
    'accessAll' => false,
    'collections' => [],
    'users' => [],
    'loading' => false,
]);

$apiService = fn() => app(BitwardenPublicApiService::class);

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

<x-intranet-app-bitwarden::bitwarden-layout heading="Neue Gruppe erstellen" subheading="Bitwarden Gruppen">
    <flux:card class="glass-card">
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="name" placeholder="Gruppenname" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="accessAll" label="Zugriff auf alle Collections" />
                <flux:error name="accessAll" />
            </flux:field>

            <div class="flex items-center justify-end gap-3 mt-6">
                <flux:button href="{{ route('apps.bitwarden.admin.groups.index') }}" variant="ghost">
                    Abbrechen
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Gruppe erstellen</span>
                    <span wire:loading>Wird erstellt...</span>
                </flux:button>
            </div>
        </form>
    </flux:card>
</x-intranet-app-bitwarden::bitwarden-layout>

