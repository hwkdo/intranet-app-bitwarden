<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Models;

use App\Models\Gvp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GvpBitwardenPref extends Model
{
    protected $table = 'intranet_app_bitwarden_gvp_prefs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'include_azubis' => 'boolean',
            'include_praktikanten' => 'boolean',
        ];
    }

    public function gvp(): BelongsTo
    {
        return $this->belongsTo(Gvp::class, 'gvp_id');
    }

    public static function forGvp(Gvp|int $gvp): self
    {
        $gvpId = $gvp instanceof Gvp ? (int) $gvp->id : $gvp;

        return self::query()->firstOrNew(
            ['gvp_id' => $gvpId],
            [
                'include_azubis' => false,
                'include_praktikanten' => false,
            ],
        );
    }
}
