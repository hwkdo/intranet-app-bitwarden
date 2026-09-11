<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Data;

use App\Models\Gvp;

class MeineSammlungCard
{
    public function __construct(
        public Gvp $gvp,
        public bool $isGesamt = false,
    ) {}

    public function title(): string
    {
        $title = $this->gvp->bezeichnung;

        if ($this->isGesamt) {
            return $title.' (Gesamt)';
        }

        return $title;
    }

    public function key(): string
    {
        return $this->gvp->id.'-'.($this->isGesamt ? 'gesamt' : 'direct');
    }
}
