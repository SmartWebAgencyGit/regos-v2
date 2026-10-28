<?php

namespace App\Providers;

use App\Hapi\FakeHapi;
use App\Hapi\Hapi;
use App\Hapi\HttpHapi;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\WorldpayGateway;
use App\Portal\AddPlayers\AddPlayersCheckout;
use App\Portal\AddPlayers\AddPlayersPricing;
use App\Portal\ConsentRules;
use App\Portal\NetworkLocator;
use App\Portal\PortalContext;
use App\Portal\PortalRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Hapi::class, function (Application $app): Hapi {
            $config = $app['config']['regos.hapi'];

            if (! $config['fake']) {
                return new HttpHapi($app->make(HttpFactory::class), $app->make(Cache::class), $config);
            }

            if (! $app->environment('local', 'testing')) {
                throw new RuntimeException('HAPI_FAKE may only be used in local or testing environments.');
            }

            return new FakeHapi($app->make(Cache::class));
        });

        $this->app->singleton(PaymentGateway::class, function (Application $app): PaymentGateway {
            return match ($app['config']['regos.payments.driver']) {
                'worldpay' => new WorldpayGateway($app->make(HttpFactory::class), $app['config']['regos.payments.worldpay']),
                'fake' => $app->environment('local', 'testing')
                    ? new FakePaymentGateway
                    : throw new RuntimeException('The fake payment gateway may only be used in local or testing environments.'),
                default => throw new InvalidArgumentException('Unknown payment driver.'),
            };
        });

        $this->app->singleton(NetworkLocator::class, fn (Application $app) => new NetworkLocator($app['config']['regos.internal_networks']));
        $this->app->singleton(ConsentRules::class, fn (Application $app) => new ConsentRules($app['config']['regos.consents']));
        $this->app->singleton(AddPlayersPricing::class, fn (Application $app) => new AddPlayersPricing($app['config']['regos']));

        $this->app->singleton(AddPlayersCheckout::class, fn (Application $app) => new AddPlayersCheckout(
            $app->make(PortalRepository::class),
            $app->make(PaymentGateway::class),
            $app['config']['regos'],
        ));

        $this->app->scoped(PortalContext::class, fn (Application $app) => new PortalContext(
            fn () => $app->make('request'),
            $app->make(PortalRepository::class),
            $app->make(NetworkLocator::class),
            $app['config']['regos'],
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($proxies = config('regos.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        RateLimiter::for('portal-entry', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('payments', fn (Request $request) => [
            Limit::perMinute(5)->by('session:'.$request->session()->getId()),
            Limit::perHour(20)->by('ip:'.$request->ip()),
        ]);
    }
}
