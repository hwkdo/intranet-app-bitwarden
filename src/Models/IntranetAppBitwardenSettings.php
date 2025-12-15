<?php

namespace Hwkdo\IntranetAppBitwarden\Models;

use Hwkdo\IntranetAppBitwarden\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;

class IntranetAppBitwardenSettings extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => AppSettings::class.':default',
        ];
    }

    public static function current(): IntranetAppBitwardenSettings|null
    {
        return self::orderBy('version', 'desc')->first();
    }
}
