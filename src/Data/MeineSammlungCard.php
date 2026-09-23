<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Data;

use App\Models\Gvp;
use Hwkdo\IntranetAppBitwarden\Models\CustomCollection;

class MeineSammlungCard
{
    public const KIND_GVP = 'gvp';

    public const KIND_CUSTOM = 'custom';

    public function __construct(
        public ?Gvp $gvp = null,
        public bool $isGesamt = false,
        public ?CustomCollection $customCollection = null,
        public string $kind = self::KIND_GVP,
    ) {}

    public static function forGvp(Gvp $gvp, bool $isGesamt = false): self
    {
        return new self(gvp: $gvp, isGesamt: $isGesamt, kind: self::KIND_GVP);
    }

    public static function forCustom(CustomCollection $collection): self
    {
        return new self(customCollection: $collection, kind: self::KIND_CUSTOM);
    }

    public function isCustom(): bool
    {
        return $this->kind === self::KIND_CUSTOM;
    }

    public function isGvp(): bool
    {
        return $this->kind === self::KIND_GVP;
    }

    public function title(): string
    {
        if ($this->isCustom()) {
            return (string) ($this->customCollection?->name ?? '');
        }

        $title = (string) ($this->gvp?->bezeichnung ?? '');

        if ($this->isGesamt) {
            return $title.' (Gesamt)';
        }

        return $title;
    }

    public function key(): string
    {
        if ($this->isCustom()) {
            return 'custom-'.(string) ($this->customCollection?->id ?? '0');
        }

        return ($this->gvp?->id ?? '0').'-'.($this->isGesamt ? 'gesamt' : 'direct');
    }
}
