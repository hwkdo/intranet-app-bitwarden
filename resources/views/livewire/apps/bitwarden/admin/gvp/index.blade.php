<?php

use App\Models\Gvp;
use Flux\Flux;
use Hwkdo\IntranetAppBitwarden\Services\GvpBitwardenProvisioningService;

use function Livewire\Volt\{state, title, computed};

title('Bitwarden - GVP verwalten');

state([
    'loading' => false,
    'creatingGroupFor' => null,
    'creatingCollectionFor' => null,
    'creatingGesamtCollectionFor' => null,
    'settingUpFor' => null,
    'search' => '',
]);

$provisioningService = computed(fn () => app(GvpBitwardenProvisioningService::class));

$gvps = computed(fn () => Gvp::query()->with('childGvps')->get());

$filteredGvps = computed(function () {
    $gvps = $this->gvps;

    if (empty($gvps)) {
        return [];
    }

    if (empty($this->search)) {
        return $gvps;
    }

    $search = strtolower($this->search);

    return $gvps->filter(function ($gvp) use ($search) {
        $bezeichnung = strtolower($gvp->bezeichnung);
        $name = strtolower($gvp->name ?? '');
        $kuerzel = strtolower($gvp->kuerzel ?? '');
        $nummer = strtolower($gvp->nummer ?? '');

        return str_contains($bezeichnung, $search)
            || str_contains($name, $search)
            || str_contains($kuerzel, $search)
            || str_contains($nummer, $search);
    })->values();
});

$createGroup = function (int $gvpId) {
    $this->creatingGroupFor = $gvpId;
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);
        $this->provisioningService()->createGroup($gvp);

        Flux::toast('Gruppe erfolgreich erstellt und Mitglieder hinzugefügt', variant: 'success');
        unset($this->gvps);
    } catch (RuntimeException $e) {
        Flux::toast($e->getMessage(), variant: 'warning');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Erstellen der Bitwarden-Gruppe', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Erstellen der Gruppe: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
        $this->creatingGroupFor = null;
    }
};

$createCollection = function (int $gvpId) {
    $this->creatingCollectionFor = $gvpId;
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);
        $this->provisioningService()->createDirectCollection($gvp);

        Flux::toast('Collection erfolgreich erstellt', variant: 'success');
        unset($this->gvps);
    } catch (RuntimeException $e) {
        Flux::toast($e->getMessage(), variant: 'warning');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Erstellen der Bitwarden-Collection', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Erstellen der Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
        $this->creatingCollectionFor = null;
    }
};

$createGesamtCollection = function (int $gvpId) {
    $this->creatingGesamtCollectionFor = $gvpId;
    $this->loading = true;

    try {
        $gvp = Gvp::with('childGvps')->findOrFail($gvpId);
        $this->provisioningService()->createGesamtCollection($gvp);

        Flux::toast('Gesamt-Collection erfolgreich erstellt', variant: 'success');
        unset($this->gvps);
    } catch (RuntimeException $e) {
        Flux::toast($e->getMessage(), variant: 'warning');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Erstellen der Gesamt-Collection', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Erstellen der Gesamt-Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
        $this->creatingGesamtCollectionFor = null;
    }
};

$setupAbteilung = function (int $gvpId) {
    $this->settingUpFor = $gvpId;
    $this->loading = true;

    try {
        $gvp = Gvp::with('childGvps')->findOrFail($gvpId);
        $result = $this->provisioningService()->setupAbteilung($gvp);

        $parts = [];

        if ($result['created_groups'] !== []) {
            $parts[] = count($result['created_groups']).' Gruppe(n)';
        }

        if ($result['created_collections'] !== []) {
            $parts[] = count($result['created_collections']).' Collection(s)';
        }

        if ($result['created_gesamt']) {
            $parts[] = 'Gesamt-Collection';
        }

        if ($parts === []) {
            $message = $result['synced_gesamt']
                ? 'Abteilung war bereits eingerichtet – Gesamt-ACL aktualisiert'
                : 'Nichts zu tun – Abteilung ist bereits eingerichtet';
            Flux::toast($message, variant: 'success');
        } else {
            Flux::toast('Setup abgeschlossen: '.implode(', ', $parts), variant: 'success');
        }

        if ($result['skipped'] !== []) {
            Flux::toast(implode(' · ', $result['skipped']), variant: 'warning');
        }

        unset($this->gvps);
    } catch (RuntimeException $e) {
        Flux::toast($e->getMessage(), variant: 'warning');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Abteilungs-Setup', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Setup: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
        $this->settingUpFor = null;
    }
};

$deleteCollection = function (int $gvpId) {
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);
        $this->provisioningService()->deleteDirectCollection($gvp);

        Flux::toast('Collection erfolgreich gelöscht', variant: 'success');
        unset($this->gvps);
    } catch (RuntimeException $e) {
        Flux::toast($e->getMessage(), variant: 'warning');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Löschen der Bitwarden-Collection', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Löschen der Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$deleteGesamtCollection = function (int $gvpId) {
    $this->loading = true;

    try {
        $gvp = Gvp::findOrFail($gvpId);
        $this->provisioningService()->deleteGesamtCollection($gvp);

        Flux::toast('Gesamt-Collection erfolgreich gelöscht', variant: 'success');
        unset($this->gvps);
    } catch (RuntimeException $e) {
        Flux::toast($e->getMessage(), variant: 'warning');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Löschen der Gesamt-Collection', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Löschen der Gesamt-Collection: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

$deleteGroup = function (int $gvpId) {
    $this->loading = true;

    try {
        $gvp = Gvp::with('parent')->findOrFail($gvpId);
        $this->provisioningService()->deleteGroup($gvp);

        Flux::toast('Gruppe erfolgreich gelöscht', variant: 'success');
        unset($this->gvps);
    } catch (RuntimeException $e) {
        Flux::toast($e->getMessage(), variant: 'warning');
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Fehler beim Löschen der Bitwarden-Gruppe', [
            'gvp_id' => $gvpId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        Flux::toast('Fehler beim Löschen der Gruppe: '.$e->getMessage(), variant: 'danger');
    } finally {
        $this->loading = false;
    }
};

?>
<div>
<x-intranet-app-bitwarden::bitwarden-layout heading="GVP verwalten" subheading="Bitwarden GVP">
    <flux:card class="glass-card">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">GVPs</flux:heading>
        </div>

        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="GVPs durchsuchen..."
            icon="magnifying-glass"
            class="mb-6"
        />

        @if(empty($this->filteredGvps))
            <flux:callout variant="info" icon="information-circle">
                @if(empty($this->search))
                    Keine GVPs gefunden.
                @else
                    Keine GVPs gefunden, die "{{ $this->search }}" enthalten.
                @endif
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Bezeichnung</flux:table.column>
                    <flux:table.column>Mitglieder</flux:table.column>
                    <flux:table.column>Gruppe</flux:table.column>
                    <flux:table.column>Collection</flux:table.column>
                    <flux:table.column>Gesamt</flux:table.column>
                    <flux:table.column align="end">Aktionen</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach($this->filteredGvps as $gvp)
                        @php
                            $memberCount = $gvp->memberCount();
                            $hasGroup = $gvp->hasBitwardenGroup();
                            $hasCollection = $gvp->hasBitwardenCollection();
                            $needsGesamt = $gvp->needsBitwardenGesamt();
                            $hasGesamt = $gvp->hasBitwardenGesamtCollection();
                            $isCreatingGroup = $this->creatingGroupFor === $gvp->id;
                            $isCreatingCollection = $this->creatingCollectionFor === $gvp->id;
                            $isCreatingGesamt = $this->creatingGesamtCollectionFor === $gvp->id;
                        @endphp
                        <flux:table.row wire:key="gvp-{{ $gvp->id }}">
                            <flux:table.cell>
                                <flux:heading size="sm">{{ $gvp->bezeichnung }}</flux:heading>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $memberCount }} {{ $memberCount === 1 ? 'Mitglied' : 'Mitglieder' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($hasGroup)
                                    <flux:badge variant="success">Ja</flux:badge>
                                @else
                                    <flux:badge variant="danger">Nein</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($hasCollection)
                                    <flux:badge variant="success">Ja</flux:badge>
                                @else
                                    <flux:badge variant="danger">Nein</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($needsGesamt)
                                    @if($hasGesamt)
                                        <flux:badge variant="success">Ja</flux:badge>
                                    @else
                                        <flux:badge variant="danger">Nein</flux:badge>
                                    @endif
                                @else
                                    <flux:text variant="muted" size="sm">—</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $isAbteilung = $gvp->kuerzel === 'A';
                                    $isSettingUp = $this->settingUpFor === $gvp->id;
                                @endphp
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    @if($isAbteilung)
                                        <flux:button.group>
                                            <flux:button
                                                wire:click="setupAbteilung({{ $gvp->id }})"
                                                variant="primary"
                                                size="sm"
                                                wire:loading.attr="disabled"
                                                wire:target="setupAbteilung({{ $gvp->id }})"
                                            >
                                                @if($isSettingUp)
                                                    Setup läuft…
                                                @else
                                                    Setup
                                                @endif
                                            </flux:button>

                                            <flux:dropdown align="end">
                                                <flux:button
                                                    variant="primary"
                                                    size="sm"
                                                    icon="chevron-down"
                                                    wire:loading.attr="disabled"
                                                    wire:target="setupAbteilung({{ $gvp->id }})"
                                                />

                                                <flux:menu>
                                                    @if(!$hasGroup && $memberCount > 0)
                                                        <flux:menu.item
                                                            icon="user-group"
                                                            wire:click="createGroup({{ $gvp->id }})"
                                                        >
                                                            Gruppe erstellen
                                                        </flux:menu.item>
                                                    @endif

                                                    @if($hasGroup && !$hasCollection)
                                                        <flux:menu.item
                                                            icon="folder"
                                                            wire:click="createCollection({{ $gvp->id }})"
                                                        >
                                                            Collection erstellen
                                                        </flux:menu.item>
                                                    @endif

                                                    @if($needsGesamt && !$hasGesamt)
                                                        <flux:menu.item
                                                            icon="folder"
                                                            wire:click="createGesamtCollection({{ $gvp->id }})"
                                                        >
                                                            Gesamt erstellen
                                                        </flux:menu.item>
                                                    @endif

                                                    @if($hasCollection || $hasGesamt || $hasGroup)
                                                        @if((!$hasGroup && $memberCount > 0) || ($hasGroup && !$hasCollection) || ($needsGesamt && !$hasGesamt))
                                                            <flux:menu.separator />
                                                        @endif

                                                        @if($hasCollection)
                                                            <flux:menu.item
                                                                icon="trash"
                                                                variant="danger"
                                                                wire:click="deleteCollection({{ $gvp->id }})"
                                                                wire:confirm="Möchten Sie die Collection für diese GVP wirklich löschen?"
                                                            >
                                                                Collection löschen
                                                            </flux:menu.item>
                                                        @endif

                                                        @if($hasGesamt)
                                                            <flux:menu.item
                                                                icon="trash"
                                                                variant="danger"
                                                                wire:click="deleteGesamtCollection({{ $gvp->id }})"
                                                                wire:confirm="Möchten Sie die Gesamt-Collection für diese GVP wirklich löschen?"
                                                            >
                                                                Gesamt löschen
                                                            </flux:menu.item>
                                                        @endif

                                                        @if($hasGroup)
                                                            <flux:menu.item
                                                                icon="trash"
                                                                variant="danger"
                                                                wire:click="deleteGroup({{ $gvp->id }})"
                                                                wire:confirm="Möchten Sie die Gruppe für diese GVP wirklich löschen?"
                                                            >
                                                                Gruppe löschen
                                                            </flux:menu.item>
                                                        @endif
                                                    @elseif(!((!$hasGroup && $memberCount > 0) || ($hasGroup && !$hasCollection) || ($needsGesamt && !$hasGesamt)))
                                                        <flux:menu.item disabled>
                                                            Keine Einzelaktionen
                                                        </flux:menu.item>
                                                    @endif
                                                </flux:menu>
                                            </flux:dropdown>
                                        </flux:button.group>
                                    @else
                                        @if(!$hasGroup && $memberCount > 0)
                                            <flux:button
                                                wire:click="createGroup({{ $gvp->id }})"
                                                variant="primary"
                                                size="sm"
                                                wire:loading.attr="disabled"
                                                wire:target="createGroup({{ $gvp->id }})"
                                            >
                                                @if($isCreatingGroup)
                                                    <span>Wird erstellt...</span>
                                                @else
                                                    Gruppe erstellen
                                                @endif
                                            </flux:button>
                                        @endif

                                        @if($hasGroup && !$hasCollection)
                                            <flux:button
                                                wire:click="createCollection({{ $gvp->id }})"
                                                variant="primary"
                                                size="sm"
                                                wire:loading.attr="disabled"
                                                wire:target="createCollection({{ $gvp->id }})"
                                            >
                                                @if($isCreatingCollection)
                                                    <span>Wird erstellt...</span>
                                                @else
                                                    Collection erstellen
                                                @endif
                                            </flux:button>
                                        @endif

                                        @if($hasCollection)
                                            <flux:button
                                                wire:click="deleteCollection({{ $gvp->id }})"
                                                wire:confirm="Möchten Sie die Collection für diese GVP wirklich löschen?"
                                                variant="ghost"
                                                icon="trash"
                                                size="sm"
                                                class="text-red-600 hover:text-red-700"
                                            >
                                                Collection löschen
                                            </flux:button>
                                        @endif

                                        @if($hasGroup)
                                            <flux:button
                                                wire:click="deleteGroup({{ $gvp->id }})"
                                                wire:confirm="Möchten Sie die Gruppe für diese GVP wirklich löschen?"
                                                variant="ghost"
                                                icon="trash"
                                                size="sm"
                                                class="text-red-600 hover:text-red-700"
                                            >
                                                Gruppe löschen
                                            </flux:button>
                                        @endif
                                    @endif
                                </div>

                                @if($hasGroup && $hasCollection && (!$needsGesamt || $hasGesamt))
                                    <flux:text variant="muted" size="sm">Vollständig eingerichtet</flux:text>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</x-intranet-app-bitwarden::bitwarden-layout>
</div>
