<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['web','auth','can:see-app-bitwarden'])->group(function () {        
    Volt::route('apps/bitwarden', 'apps.bitwarden.index')->name('apps.bitwarden.index');
    Volt::route('apps/bitwarden/example', 'apps.bitwarden.example')->name('apps.bitwarden.example');
    Volt::route('apps/bitwarden/settings/user', 'apps.bitwarden.settings.user')->name('apps.bitwarden.settings.user');
});

Route::middleware(['web','auth','can:manage-app-bitwarden'])->group(function () {
    Volt::route('apps/bitwarden/admin', 'apps.bitwarden.admin.index')->name('apps.bitwarden.admin.index');
    
    // Gruppen-Routes
    Volt::route('apps/bitwarden/admin/groups', 'apps.bitwarden.admin.groups.index')->name('apps.bitwarden.admin.groups.index');
    Volt::route('apps/bitwarden/admin/groups/create', 'apps.bitwarden.admin.groups.create')->name('apps.bitwarden.admin.groups.create');
    Volt::route('apps/bitwarden/admin/groups/{groupId}', 'apps.bitwarden.admin.groups.show')->name('apps.bitwarden.admin.groups.show');
    Volt::route('apps/bitwarden/admin/groups/{groupId}/edit', 'apps.bitwarden.admin.groups.edit')->name('apps.bitwarden.admin.groups.edit');
    
    // Mitglieder-Routes
    Volt::route('apps/bitwarden/admin/members', 'apps.bitwarden.admin.members.index')->name('apps.bitwarden.admin.members.index');
    Volt::route('apps/bitwarden/admin/members/invite', 'apps.bitwarden.admin.members.invite')->name('apps.bitwarden.admin.members.invite');
    Volt::route('apps/bitwarden/admin/members/{memberId}', 'apps.bitwarden.admin.members.show')->name('apps.bitwarden.admin.members.show');
    Volt::route('apps/bitwarden/admin/members/{memberId}/edit', 'apps.bitwarden.admin.members.edit')->name('apps.bitwarden.admin.members.edit');
    
    // Collections-Routes
    Volt::route('apps/bitwarden/admin/collections', 'apps.bitwarden.admin.collections.index')->name('apps.bitwarden.admin.collections.index');
    Volt::route('apps/bitwarden/admin/collections/create', 'apps.bitwarden.admin.collections.create')->name('apps.bitwarden.admin.collections.create');
    Volt::route('apps/bitwarden/admin/collections/{collectionId}', 'apps.bitwarden.admin.collections.show')->name('apps.bitwarden.admin.collections.show');
    Volt::route('apps/bitwarden/admin/collections/{collectionId}/edit', 'apps.bitwarden.admin.collections.edit')->name('apps.bitwarden.admin.collections.edit');
    
    // GVP-Routes
    Volt::route('apps/bitwarden/admin/gvp', 'apps.bitwarden.admin.gvp.index')->name('apps.bitwarden.admin.gvp.index');
});
