<?php

use Flux\Flux;
use Hwkdo\BitwardenLaravel\Contracts\BitwardenManagementApiInterface;

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

$apiService = computed(fn() => app(BitwardenManagementApiInterface::class));
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
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="Mitglied einladen" subheading="Senden Sie eine Einladung in die Bitwarden-Organisation." icon="users">
    <x-intranet-app-bitwarden::section icon="users" title="Einladung" description="E-Mail, Typ und optional die Gruppen.">
        <form wire:submit="save">
            <div class="divide-y divide-[#d0e3f9] px-5 dark:divide-white/10">
                <x-intranet-app-bitwarden::row label="E-Mail-Adressen" description="Eine Adresse pro Feld. Weitere Adressen fügen Sie darunter hinzu." wide>
                    @foreach($emails as $index => $email)
                        <div class="mb-2 flex gap-2" wire:key="invite-email-{{ $index }}">
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
                </x-intranet-app-bitwarden::row>
                <x-intranet-app-bitwarden::row label="Typ" description="Rolle in der Organisation." wide>
                    <flux:select wire:model="type" required>
                        <option value="0">Owner</option>
                        <option value="1">Admin</option>
                        <option value="2">User</option>
                        <option value="3">Manager</option>
                        <option value="4">Custom</option>
                    </flux:select>
                    <flux:error name="type" />
                </x-intranet-app-bitwarden::row>
                <x-intranet-app-bitwarden::row label="Zugriff" description="Zugriff auf alle Collections.">
                    <flux:checkbox wire:model="accessAll" label="Alle Collections" />
                    <flux:error name="accessAll" />
                </x-intranet-app-bitwarden::row>
                @if(!empty($availableGroups))
                    <x-intranet-app-bitwarden::row label="Gruppen" description="Gruppen, denen die Einladung zugeordnet wird." wide>
                        <flux:select wire:model="groups" multiple>
                            @foreach($availableGroups as $group)
                                <option value="{{ $group['id'] }}" wire:key="invite-group-{{ $group['id'] }}">{{ $group['name'] ?? 'Unbenannt' }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="groups" />
                    </x-intranet-app-bitwarden::row>
                @endif
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-[#d0e3f9] px-5 py-4 dark:border-white/10">
                <flux:button href="{{ route('apps.bitwarden.admin.members.index') }}" variant="ghost">
                    Abbrechen
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Einladung(en) versenden</span>
                    <span wire:loading>Wird versendet …</span>
                </flux:button>
            </div>
        </form>
    </x-intranet-app-bitwarden::section>
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
