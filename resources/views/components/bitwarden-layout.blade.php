@props([
    'heading' => '',
    'subheading' => '',
    'navItems' => []
])

@php
    $defaultNavItems = [
        ['label' => 'Übersicht', 'href' => route('apps.bitwarden.index'), 'icon' => 'home', 'description' => 'Zurück zur Übersicht', 'buttonText' => 'Übersicht anzeigen'],
        ['label' => 'Beispielseite', 'href' => route('apps.bitwarden.example'), 'icon' => 'document-text', 'description' => 'Beispielseite anzeigen', 'buttonText' => 'Beispielseite öffnen'],
        ['label' => 'Meine Einstellungen', 'href' => route('apps.bitwarden.settings.user'), 'icon' => 'cog-6-tooth', 'description' => 'Persönliche Einstellungen anpassen', 'buttonText' => 'Einstellungen öffnen'],
        ['label' => 'Admin', 'href' => route('apps.bitwarden.admin.index'), 'icon' => 'shield-check', 'description' => 'Administrationsbereich verwalten', 'buttonText' => 'Admin öffnen', 'permission' => 'manage-app-bitwarden'],
        ['label' => 'Gruppen', 'href' => route('apps.bitwarden.admin.groups.index'), 'icon' => 'user-group', 'description' => 'Gruppen verwalten', 'buttonText' => 'Gruppen öffnen', 'permission' => 'manage-app-bitwarden'],
        ['label' => 'Mitglieder', 'href' => route('apps.bitwarden.admin.members.index'), 'icon' => 'users', 'description' => 'Mitglieder verwalten', 'buttonText' => 'Mitglieder öffnen', 'permission' => 'manage-app-bitwarden'],
        ['label' => 'Collections', 'href' => route('apps.bitwarden.admin.collections.index'), 'icon' => 'folder', 'description' => 'Collections verwalten', 'buttonText' => 'Collections öffnen', 'permission' => 'manage-app-bitwarden'],
        ['label' => 'GVP', 'href' => route('apps.bitwarden.admin.gvp.index'), 'icon' => 'building-office', 'description' => 'GVP verwalten', 'buttonText' => 'GVP öffnen', 'permission' => 'manage-app-bitwarden']
    ];
    
    $navItems = !empty($navItems) ? $navItems : $defaultNavItems;
@endphp

@if(request()->routeIs('apps.bitwarden.index'))
    <x-intranet-app-base::app-layout 
        app-identifier="bitwarden"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="false"
    >
        <x-intranet-app-base::app-index-auto 
            app-identifier="bitwarden"
            app-name="Bitwarden App"
            app-description="Generated app: Bitwarden"
            :nav-items="$navItems"
            welcome-title="Willkommen zur Bitwarden App"
            welcome-description="Dies ist eine Beispiel-App, die als Bitwarden für neue Intranet-Apps dient."
        />
    </x-intranet-app-base::app-layout>
@else
    <x-intranet-app-base::app-layout 
        app-identifier="bitwarden"
        :heading="$heading"
        :subheading="$subheading"
        :nav-items="$navItems"
        :wrap-in-card="true"
    >
        {{ $slot }}
    </x-intranet-app-base::app-layout>
@endif
