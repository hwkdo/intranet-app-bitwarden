<?php

namespace Hwkdo\IntranetAppBitwarden;

use App\Models\Gvp;
use App\Models\User;
use Hwkdo\IntranetAppBitwarden\Services\GvpBitwardenMembershipService;
use Livewire\Volt\Volt;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IntranetAppBitwardenServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('intranet-app-bitwarden')
            ->hasConfigFile()
            ->hasViews()
            ->discoversMigrations();
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->booted(function (): void {
            Volt::mount(__DIR__.'/../resources/views/livewire');

            if (! class_exists(User::class) || ! class_exists(Gvp::class)) {
                return;
            }

            User::created(function (User $user): void {
                if (! $user->active || $user->gvp_id === null) {
                    return;
                }

                $gvp = Gvp::find($user->gvp_id);

                if ($gvp === null || ! $gvp->hasBitwardenGroup()) {
                    return;
                }

                app(GvpBitwardenMembershipService::class)->syncGroupMembers($gvp);
            });

            User::updated(function (User $user): void {
                if (! $user->wasChanged('gvp_id') && ! $user->wasChanged('active')) {
                    return;
                }

                $originalGvpId = $user->getOriginal('gvp_id');
                $currentGvpId = $user->gvp_id;

                $gvpIds = array_values(array_unique(array_filter([
                    $originalGvpId,
                    $currentGvpId,
                ], static fn ($id): bool => $id !== null)));

                if ($gvpIds === []) {
                    return;
                }

                $gvps = Gvp::query()
                    ->whereIn('id', $gvpIds)
                    ->get()
                    ->filter(static fn (Gvp $gvp): bool => $gvp->hasBitwardenGroup());

                if ($gvps->isEmpty()) {
                    return;
                }

                /** @var GvpBitwardenMembershipService $service */
                $service = app(GvpBitwardenMembershipService::class);

                foreach ($gvps as $gvp) {
                    $service->syncGroupMembers($gvp);
                }
            });
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}

