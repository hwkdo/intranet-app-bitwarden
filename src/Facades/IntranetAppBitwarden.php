<?php

namespace Hwkdo\IntranetAppBitwarden\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppBitwarden\IntranetAppBitwarden
 */
class IntranetAppBitwarden extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppBitwarden\IntranetAppBitwarden::class;
    }
}
