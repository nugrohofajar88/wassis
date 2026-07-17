<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Message;
use App\Services\Memory\MemoryEngine;
use App\Services\WhatsApp\WhatsAppGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        protected WhatsAppGatewayInterface $gateway,
        protected MemoryEngine $memoryEngine
    ) {}

    /**
     * List the conversation with a contact, most recent last.
     */
    public function index(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeOwnership($request, $contact);

        $messages = $contact->messages()
            ->orderBy('created_at')
            ->paginate(50);

        return response()->json(['messages' => $messages]);
    }

    /**
     * Send an outbound message to a contact via the active WhatsApp gateway.
     */
    public function store(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeOwnership($request, $contact);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $response = $this->gateway->sendMessage($contact->phone, $validated['content']);
        $success  = (bool) ($response['status'] ?? false);

        $message = $contact->messages()->create([
            'user_id'             => $request->user()->id,
            'direction'           => 'out',
            'content'             => $validated['content'],
            'status'              => $success ? 'sent' : 'failed',
            'gateway_message_id'  => $response['id'] ?? null,
            'sent_at'             => $success ? now() : null,
        ]);

        return response()->json([
            'message'  => $message,
            'gateway'  => $response,
        ], $success ? 201 : 502);
    }

    /**
     * Generate an AI-suggested reply based on the recent conversation, style profile and memories.
     */
    public function suggestReply(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeOwnership($request, $contact);

        $validated = $request->validate([
            'instruction' => 'nullable|string',
        ]);

        $recent = $contact->messages()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->sortBy('created_at');

        $conversation = $recent
            ->map(fn (Message $m) => ($m->direction === 'in' ? $contact->name : 'Me') . ': ' . $m->content)
            ->implode("\n");

        $reply = $this->memoryEngine->suggestReply(
            $request->user(),
            $contact,
            $conversation,
            $validated['instruction'] ?? ''
        );

        return response()->json(['suggested_reply' => $reply]);
    }

    /**
     * Abort with 404 if the contact does not belong to the authenticated user.
     */
    protected function authorizeOwnership(Request $request, Contact $contact): void
    {
        abort_unless($contact->user_id === $request->user()->id, 404);
    }
}
