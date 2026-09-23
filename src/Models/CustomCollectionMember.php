<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomCollectionMember extends Model
{
    protected $table = 'intranet_app_bitwarden_custom_collection_members';

    protected $guarded = [];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(CustomCollection::class, 'custom_collection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
