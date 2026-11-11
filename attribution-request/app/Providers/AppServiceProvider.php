<?php

namespace App\Providers;

use App\Models\Device;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\SimLine;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureModels();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        Carbon::setLocale(config('app.locale'));
        CarbonImmutable::setLocale(config('app.locale'));

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureModels(): void
    {
        // Hors production : lazy loading en boucle, attributs ignorés ou inexistants → exception immédiate.
        Model::shouldBeStrict(! app()->isProduction());

        // En local, un N+1 est signalé dans les logs sans casser l'écran ; en test il reste bloquant.
        if (app()->isLocal()) {
            Model::handleLazyLoadingViolationUsing(
                fn (Model $model, string $relation) => logger()->warning('Lazy loading (N+1) : '.$model::class.'::'.$relation),
            );
        }

        // Les colonnes *_type stockent un alias stable, pas un nom de classe PHP.
        Relation::enforceMorphMap([
            'user' => User::class,
            'device' => Device::class,
            'sim_line' => SimLine::class,
            'service_request' => ServiceRequest::class,
            'order' => Order::class,
        ]);
    }
}
