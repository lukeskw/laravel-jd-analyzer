<?php

namespace App\Providers;

use App\Adapters\FakeLLMClient;
use App\Adapters\PrismLLMClient;
use App\Adapters\SpatiePdfToTextAdapter;
use App\Contracts\LLMClientInterface;
use App\Contracts\PdfParserInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PdfParserInterface::class, SpatiePdfToTextAdapter::class);

        $this->app->bind(LLMClientInterface::class, function ($app) {
            $driver = (string) config('llm.driver', 'fake');

            return match ($driver) {
                'prism' => new PrismLLMClient,
                default => new FakeLLMClient,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
