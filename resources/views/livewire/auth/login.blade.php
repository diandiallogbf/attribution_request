<x-layouts::auth :title="__('Connexion')">
    <div class="flex flex-col gap-8">
        <x-auth-header
            :title="__('Bienvenue')"
            :description="__('Connectez-vous avec votre adresse e-mail professionnelle AGL.')"
        />

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                :label="__('Adresse e-mail')"
                :value="old('email')"
                type="email"
                icon="envelope"
                required
                autofocus
                autocomplete="email"
                placeholder="prenom.nom@aglgroup.com"
            />

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <flux:label for="password">{{ __('Mot de passe') }}</flux:label>
                    @if (Route::has('password.request'))
                        <flux:link class="text-sm" :href="route('password.request')" wire:navigate variant="subtle">
                            {{ __('Mot de passe oublié ?') }}
                        </flux:link>
                    @endif
                </div>
                <flux:input
                    id="password"
                    name="password"
                    type="password"
                    icon="lock-closed"
                    required
                    autocomplete="current-password"
                    viewable
                />
            </div>

            <flux:checkbox name="remember" :label="__('Rester connecté sur cet appareil')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="mt-1 w-full" data-test="login-button">
                {{ __('Se connecter') }}
            </flux:button>
        </form>

        <div class="flex items-start gap-3 rounded-lg border border-dashed border-zinc-300 p-3 text-xs leading-relaxed text-zinc-500 dark:border-white/15 dark:text-zinc-400">
            <flux:icon.information-circle variant="mini" class="mt-px size-4 shrink-0 text-zinc-400" />
            <p>{{ __('Les comptes sont créés par la DSI. La connexion avec votre compte Microsoft (SSO) arrivera prochainement.') }}</p>
        </div>
    </div>
</x-layouts::auth>
