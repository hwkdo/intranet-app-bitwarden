<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('intranet_app_bitwarden_custom_collections')) {
            Schema::create('intranet_app_bitwarden_custom_collections', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('bitwarden_collection_id');
                $table->string('external_id');
                $table->unsignedBigInteger('created_by_user_id');
                $table->timestamps();

                $table->unique('bitwarden_collection_id', 'iab_cc_bw_coll_uq');
                $table->unique('external_id', 'iab_cc_ext_id_uq');
                $table->index('created_by_user_id', 'iab_cc_creator_idx');
            });
        }

        if (! Schema::hasTable('intranet_app_bitwarden_custom_collection_members')) {
            Schema::create('intranet_app_bitwarden_custom_collection_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('custom_collection_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->unique(['custom_collection_id', 'user_id'], 'iab_ccm_coll_user_uq');
                $table->index('user_id', 'iab_ccm_user_idx');
                $table->index('custom_collection_id', 'iab_ccm_coll_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bitwarden_custom_collection_members');
        Schema::dropIfExists('intranet_app_bitwarden_custom_collections');
    }
};
