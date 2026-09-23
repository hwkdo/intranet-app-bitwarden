<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomCollection extends Model
{
    protected $table = 'intranet_app_bitwarden_custom_collections';

    protected $guarded = [];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CustomCollectionMember::class, 'custom_collection_id');
    }

    public function isCreatedBy(User $user): bool
    {
        return (int) $this->created_by_user_id === (int) $user->id;
    }
}
