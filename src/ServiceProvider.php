<?php


namespace JWTAuth;

use Illuminate\Support\Facades\Auth;
use JWTAuth\Contracts\JwtBlockListContract;
use JWTAuth\Exceptions\JWTConfigurationException;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Register any application authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/jwt-auth.php' => config_path('jwt-auth.php'),
            ], 'config');

            if (!class_exists('CreateJwtTokensTables')) {
                $this->publishes([
                    __DIR__ . '/../database/migrations/create_jwt_tokens_store_tables.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_jwt_tokens_store_tables.php'),
                ], 'migrations');
            }


            $this->commands([
                \JWTAuth\Console\PruneTokensStorageCommand::class,
                \JWTAuth\Console\PruneBlockListCommand::class,
                \JWTAuth\Console\GenerateJWTKeysCommand::class,
            ]);
        }

        Auth::extend('jwt', function ($app, $name, array $config) {
            if (!$config['provider']) {
                throw new JWTConfigurationException('Not valid "provider" value.');
            }

            $blocklistProvider = $app['config']->get("jwt-auth.blocklist.providers.{$config['blocklist']}");
            if (!$blocklistProvider || empty($blocklistProvider['driver'])  || !class_exists($blocklistProvider['driver']) || !is_subclass_of($blocklistProvider['driver'], JwtBlockListContract::class)) {
                throw new JWTConfigurationException('blocklist should implement JwtBlockList Interface');
            }

            return new JWTGuard(
                Auth::createUserProvider($config['provider']),
                new JWTManager(($config['public_key']                         ?? ''), ($config['private_key']                         ?? '')),
                new $blocklistProvider['driver']($blocklistProvider['options']??[]),
                $config['options']                                            ?? []
            );
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/jwt-auth.php', 'jwt-auth');
    }
}
