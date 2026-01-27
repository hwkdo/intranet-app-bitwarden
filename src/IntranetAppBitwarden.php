<?php

namespace Hwkdo\IntranetAppBitwarden;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Illuminate\Support\Collection;

class IntranetAppBitwarden implements IntranetAppInterface 
{
    public static function app_name(): string
    {
        return 'Bitwarden';
    }

    public static function app_icon(): string
    {
        return 'magnifying-glass';
    }

    public static function identifier(): string
    {
        return 'bitwarden';
    }

    public static function roles_admin(): Collection
    {
        return collect(config('intranet-app-bitwarden.roles.admin'));
    }

    public static function roles_user(): Collection
    {
        return collect(config('intranet-app-bitwarden.roles.user'));
    }
    
    public static function userSettingsClass(): ?string
    {
        return \Hwkdo\IntranetAppBitwarden\Data\UserSettings::class;
    }
    
    public static function appSettingsClass(): ?string
    {
        return \Hwkdo\IntranetAppBitwarden\Data\AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
    }
}
