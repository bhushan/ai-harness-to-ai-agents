<?php

namespace App\AI\Tools;

use App\Models\Customer;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Log;

final class CreateTicket implements Tool
{
    public function name(): string
    {
        return 'create_ticket';
    }

    public function impact(): ToolImpact
    {
        return ToolImpact::Write;
    }

    public function permission(): string
    {
        return 'tickets.create';
    }

    public function approvalPermission(): ?string
    {
        return null;
    }

    public function description(): string
    {
        return 'Open a support ticket for a human to act on. Use this when something needs a person: '
            .'a refund decision, a billing correction, anything you are not allowed to do yourself. '
            .'Put the evidence in the body, including payment ids and amounts, so the human does not '
            .'have to repeat your investigation.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer_id' => [
                    'type' => 'integer',
                    'description' => 'The id of the customer the ticket is about.',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'One line summarising the issue.',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => 'The full explanation, including the evidence you found.',
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'normal', 'high'],
                    'description' => 'Use high only when the customer is out of pocket right now.',
                ],
            ],
            'required' => ['customer_id', 'subject', 'body', 'priority'],
        ];
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'min:1'],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'priority' => ['required', 'string', 'in:low,normal,high'],
        ];
    }

    public function execute(array $input): ToolResult
    {
        if (! Customer::whereKey($input['customer_id'])->exists()) {
            return ToolResult::failed('No customer with id '.$input['customer_id'].'.');
        }

        $ticket = SupportTicket::create([
            'customer_id' => $input['customer_id'],
            'subject' => $input['subject'],
            'body' => $input['body'],
            'priority' => $input['priority'],
            'status' => 'open',
            'opened_by' => 'assistant',
        ]);

        Log::info('tool.create_ticket', [
            'customer_id' => $input['customer_id'],
            'ticket_id' => $ticket->id,
            'priority' => $ticket->priority,
        ]);

        return ToolResult::ok([
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'subject' => $ticket->subject,
        ], 'ticket #'.$ticket->id.' opened');
    }
}
