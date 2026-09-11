<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('intranet_app_bitwarden_gvp_prefs')) {
            Schema::create('intranet_app_bitwarden_gvp_prefs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('gvp_id');
                $table->boolean('include_azubis')->default(false);
                $table->boolean('include_praktikanten')->default(false);
                $table->timestamps();

                $table->unique('gvp_id', 'iab_gvp_prefs_gvp_uq');
            });
        }

        if (! Schema::hasTable('intranet_app_bitwarden_gvp_exclusions')) {
            Schema::create('intranet_app_bitwarden_gvp_exclusions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('gvp_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->unique(['gvp_id', 'user_id'], 'iab_gvp_excl_gvp_user_uq');
                $table->index('user_id', 'iab_gvp_excl_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_bitwarden_gvp_exclusions');
        Schema::dropIfExists('intranet_app_bitwarden_gvp_prefs');
    }
};
