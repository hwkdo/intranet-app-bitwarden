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
});
