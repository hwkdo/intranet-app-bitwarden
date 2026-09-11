<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Models;

use App\Models\Gvp;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GvpBitwardenExclusion extends Model
{
    protected $table = 'intranet_app_bitwarden_gvp_exclusions';

    protected $guarded = [];

    public function gvp(): BelongsTo
    {
        return $this->belongsTo(Gvp::class, 'gvp_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function isExcluded(int $gvpId, int $userId): bool
    {
        return self::query()
            ->where('gvp_id', $gvpId)
            ->where('user_id', $userId)
            ->exists();
    }
}
