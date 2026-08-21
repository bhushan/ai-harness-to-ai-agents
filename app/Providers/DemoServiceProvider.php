<?php

namespace App\Providers;

use App\AI\AnthropicClient;
use App\AI\Transport\FixtureTransport;
use App\AI\Transport\LlmTransport;
use App\Billing\FixtureStripeGateway;
use App\Billing\StripeGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the two outside worlds this application talks to.
 *
 * Both are fixture backed. There is no HTTP implementation to swap in, which is
 * the whole point: the demo cannot reach the network even by accident.
 */
class DemoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmTransport::class, fn () => new FixtureTransport(
            fixturePath: config('demo.fixtures.llm'),
            recordPath: config('demo.record_requests_to'),
        ));

        $this->app->singleton(AnthropicClient::class, fn ($app) => new AnthropicClient(
            transport: $app->make(LlmTransport::class),
            model: config('demo.model'),
            maxTokens: (int) config('demo.max_tokens'),
        ));

        $this->app->singleton(StripeGateway::class, fn () => new FixtureStripeGateway(
            fixturePath: config('demo.fixtures.stripe'),
        ));
    }
}
