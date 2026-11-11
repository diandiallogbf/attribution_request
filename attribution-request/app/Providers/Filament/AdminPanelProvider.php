<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Back-office DSI : approbation, commandes, parc, référentiels.
 * Même authentification que le portail (pas de second écran de connexion).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Back-office DSI')
            ->brandLogo(asset('images/brand/agl-wordmark.png'))
            ->darkModeBrandLogo(asset('images/brand/agl-wordmark-white.png'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon.ico'))
            ->font('Inter')
            ->colors([
                // Palette AGL : le 600 (boutons, liens) est exactement le bleu du logo #1C355E.
                'primary' => $this->brandPalette(),
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'orange' => Color::Orange,
                'violet' => Color::Violet,
            ])
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->navigationGroups([
                NavigationGroup::make('Demandes'),
                NavigationGroup::make('Parc'),
                NavigationGroup::make('Référentiels'),
                NavigationGroup::make('Organisation'),
            ])
            ->navigationItems([
                NavigationItem::make('Portail collaborateur')
                    ->url(fn (): string => route('dashboard'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->sort(100),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /** @return array<int, string> */
    private function brandPalette(): array
    {
        return array_map(fn (string $hex): string => Color::convertToOklch($hex), [
            50 => '#f1f5fb',
            100 => '#e2e9f4',
            200 => '#c5d3e8',
            300 => '#9cb2d5',
            400 => '#6b89bb',
            500 => '#375285',
            600 => '#1c355e',
            700 => '#172d50',
            800 => '#132643',
            900 => '#0f1f37',
            950 => '#0a1526',
        ]);
    }
}
