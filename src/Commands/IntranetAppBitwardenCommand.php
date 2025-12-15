<?php

namespace Hwkdo\IntranetAppBitwarden\Commands;

use Illuminate\Console\Command;

class IntranetAppBitwardenCommand extends Command
{
    public $signature = 'intranet-app-bitwarden';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
