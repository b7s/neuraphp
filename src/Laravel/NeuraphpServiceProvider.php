<?php

declare(strict_types=1);

namespace B7s\Neuraphp\Laravel;

use B7s\Neuraphp\Config;
use B7s\Neuraphp\Enums\Model;
use B7s\Neuraphp\Enums\Quantization;
use B7s\Neuraphp\Neuraphp;
use B7s\Neuraphp\NeuraphpService;
use Illuminate\Support\ServiceProvider;

final class NeuraphpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/neuraphp.php',
            'neuraphp',
        );

        $this->app->singleton(NeuraphpService::class, function (): NeuraphpService {
            $config = $this->resolveConfig();

            return NeuraphpService::fromConfig($config);
        });

        $this->app->singleton(Neuraphp::class, fn (): Neuraphp => Neuraphp::make());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__.'/../../config/neuraphp.php' => config_path('neuraphp.php')],
                'neuraphp-config',
            );
        }
    }

    private function resolveConfig(): Config
    {
        $config = Config::resolve();

        $laravelConfig = $this->app->make('config')->get('neuraphp', []);

        if (isset($laravelConfig['model']) && is_string($laravelConfig['model'])) {
            $config = $config->withModel(Model::from($laravelConfig['model']));
        }

        if (isset($laravelConfig['quantization']) && is_string($laravelConfig['quantization'])) {
            $config = $config->withQuantization(Quantization::from($laravelConfig['quantization']));
        }

        if (isset($laravelConfig['threads']) && is_int($laravelConfig['threads'])) {
            $config = $config->withThreads($laravelConfig['threads']);
        }

        if (isset($laravelConfig['model_path']) && is_string($laravelConfig['model_path'])) {
            $config = $config->withModelPath($laravelConfig['model_path']);
        }

        if (isset($laravelConfig['library_path']) && is_string($laravelConfig['library_path'])) {
            $config = $config->withLibraryPath($laravelConfig['library_path']);
        }

        return $config;
    }
}
