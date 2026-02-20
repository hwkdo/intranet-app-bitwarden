<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Services\BitwardenPublicApiService;

use function Livewire\Volt\{state, title, computed};

title('Bitwarden - Mitglied einladen');

state([
    'emails' => [''],
    'type' => '2',
    'accessAll' => false,
    'collections' => [],
    'groups' => [],
    'loading' => false,
]);

$apiService = computed(fn() => app(BitwardenPublicApiService::class));
$availableGroups = computed(function () {
    try {
        return $this->apiService()->getGroups();
    } catch (\Exception $e) {
        return [];
    }
});

$addEmailField = function () {
    $this->emails[] = '';
};

$removeEmailField = function (int $index) {
    unset($this->emails[$index]);
    $this->emails = array_values($this->emails);
};

$save = function () {
    $this->validate([
        'emails' => 'required|array|min:1',
        'emails.*' => 'required|email',
        'type' => 'required|string|in:0,1,2,3,4,Owner,Admin,User,Manager,Custom',
        'accessAll' => 'boolean',
        'collections' => 'array',
        'groups' => 'array',
    ]);

    // Filtere leere E-Mail-Adressen
    $validEmails = array_filter($this->emails, fn($email) => ! empty(trim($email)));

    if (empty($validEmails)) {
        Flux::toast('Bitte geben Sie mindestens eine gültige E-Mail-Adresse ein', variant: 'danger');
        return;
    }

    $this->loading = true;
    try {
        $this->apiService()->inviteMembers([
            'emails' => array_values($validEmails),
            'type' => $this->type,
            'accessAll' => $this->accessAll,
            'collections' => $this->collections,
            'groups' => $this->groups,
        ]);

        Flux::toast('Einladung(en) erfolgreich versendet', variant: 'success');
        $this->dispatch('member-created');
        return redirect()->route('apps.bitwarden.admin.members.index');
    } catch (\Exception $e) {
        Flux::toast('Fehler beim Versenden der Einladung(en): '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

?>

<x-intranet-app-bitwarden::bitwarden-layout heading="Mitglied einladen" subheading="Bitwarden Mitglieder">
    <flux:card class="glass-card">
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>E-Mail-Adressen</flux:label>
                @foreach($emails as $index => $email)
                    <div class="flex gap-2 mb-2">
                        <flux:input
                            wire:model="emails.{{ $index }}"
                            type="email"
                            placeholder="email@example.com"
                            class="flex-1"
                            required
                        />
                        @if(count($emails) > 1)
                            <flux:button
                                wire:click="removeEmailField({{ $index }})"
                                variant="ghost"
                                icon="trash"
                                type="button"
                            />
                        @endif
                    </div>
                @endforeach
                <flux:button
                    wire:click="addEmailField"
                    variant="ghost"
                    icon="plus"
                    type="button"
                    class="mt-2"
                >
                    Weitere E-Mail-Adresse hinzufügen
                </flux:button>
                <flux:error name="emails" />
            </flux:field>

            <flux:field>
                <flux:label>Typ</flux:label>
                <flux:select wire:model="type" required>
                    <option value="0">Owner</option>
                    <option value="1">Admin</option>
                    <option value="2">User</option>
                    <option value="3">Manager</option>
                    <option value="4">Custom</option>
                </flux:select>
                <flux:error name="type" />
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="accessAll" label="Zugriff auf alle Collections" />
                <flux:error name="accessAll" />
            </flux:field>

            @if(!empty($availableGroups))
                <flux:field>
                    <flux:label>Gruppen</flux:label>
                    <flux:select wire:model="groups" multiple>
                        @foreach($availableGroups as $group)
                            <option value="{{ $group['id'] }}">{{ $group['name'] ?? 'Unbenannt' }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="groups" />
                </flux:field>
            @endif

            <div class="flex items-center justify-end gap-3 mt-6">
                <flux:button href="{{ route('apps.bitwarden.admin.members.index') }}" variant="ghost">
                    Abbrechen
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Einladung(en) versenden</span>
                    <span wire:loading>Wird versendet...</span>
                </flux:button>
            </div>
        </form>
    </flux:card>
</x-intranet-app-bitwarden::bitwarden-layout>

