<?php

namespace App\Providers;

use App\AI\AnthropicClient;
use App\AI\Transport\FixtureTransport;
use App\AI\Tools\CreateTicket;
use App\AI\Tools\GetCustomer;
use App\AI\Tools\GetOrders;
use App\AI\Tools\GetPayments;
use App\AI\Tools\ToolRegistry;
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

        // Registration order is the order the model sees them in, and it stays
        // stable so the request payload is identical on every run.
        $this->app->singleton(ToolRegistry::class, fn ($app) => (new ToolRegistry)
            ->register($app->make(GetCustomer::class))
            ->register($app->make(GetOrders::class))
            ->register($app->make(GetPayments::class))
            ->register($app->make(CreateTicket::class)));

        $this->app->singleton(StripeGateway::class, fn () => new FixtureStripeGateway(
            fixturePath: config('demo.fixtures.stripe'),
        ));
    }
}
