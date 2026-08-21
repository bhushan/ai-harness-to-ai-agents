<?php

namespace App\Console\Commands;

use App\AI\AnthropicClient;
use App\AI\MessagesRequest;
use App\Support\DemoPrinter;
use Illuminate\Console\Command;

/**
 * Step 1. A model, a question, and nothing else.
 */
class DemoLlmCommand extends Command
{
    protected $signature = 'demo:llm';

    protected $description = 'Ask the model the raw question, with no context and no tools';

    public const SCENARIO = 'llm-raw';

    public const QUESTION = 'Why was I charged twice?';

    public function handle(AnthropicClient $client): int
    {
        $printer = DemoPrinter::for($this->output);

        $printer->title('Step 1: the model on its own', 'No system prompt, no context, no tools');

        $request = MessagesRequest::make()->withUserMessage(self::QUESTION);

        $response = $client->send(self::SCENARIO, $request);

        $printer->request($response->request());
        $printer->answer($response->text());

        $printer->section('<<< RESPONSE METADATA', '', 'green;options=bold');
        $printer->kv('model', $response->model());
        $printer->kv('stop_reason', $response->stopReason());
        $printer->kv('input tokens', (string) $response->inputTokens());
        $printer->kv('output tokens', (string) $response->outputTokens());

        $printer->section('WHAT TO NOTICE');
        $printer->bullet('The request is three fields. A model id, a token ceiling, and one');
        $printer->note('   message. That is the whole thing.');
        $printer->blank();
        $printer->bullet('The answer is not wrong. It is a decent support article. It also');
        $printer->note('   never mentions Priya Sharma, ₹999, or payments 123 and 124,');
        $printer->note('   because nothing in the request said they exist.');
        $printer->blank();
        $printer->bullet('Everything left in this talk is about closing that gap.');
        $printer->blank();

        return self::SUCCESS;
    }
}
