<?php

use App\Models\User;
use Flux\Flux;
use Hwkdo\IntranetAppBitwarden\Services\UserApiKeyVaultService;
use Hwkdo\IntranetAppBitwarden\Support\UserSecretKind;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new class extends Component
{
    #[Reactive]
    public string $kind = '';

    #[Reactive]
    public string $secret = '';

    #[Computed]
    public function status(): string
    {
        if (trim($this->secret) === '' || ! UserSecretKind::isValid($this->kind)) {
            return 'blank';
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return 'blank';
        }

        return app(UserApiKeyVaultService::class)->status($user);
    }

    public function store(): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        try {
            app(UserApiKeyVaultService::class)->store($user, $this->kind, $this->secret);
            Flux::toast('Schlüssel in Bitwarden gespeichert', variant: 'success');
        } catch (RuntimeException $exception) {
            Flux::toast($exception->getMessage(), variant: 'danger');
        } catch (Throwable $exception) {
            Flux::toast('Der Schlüssel konnte nicht in Bitwarden gespeichert werden.', variant: 'danger');
        }
    }
}; ?>

<div>
    @if($this->status === 'eligible')
        <div class="mt-2">
            <flux:button
                type="button"
                size="sm"
                icon="key"
                wire:click="store"
                wire:confirm="Schlüssel in Bitwarden speichern?"
                wire:loading.attr="disabled"
            >
                In Bitwarden speichern
            </flux:button>
        </div>
    @elseif($this->status === 'ineligible')
        <flux:text class="mt-2 text-sm text-zinc-500">
            Speichern in Bitwarden ist verfügbar, sobald dein Konto in der Organisation bestätigt ist.
        </flux:text>
    @endif
</div>
