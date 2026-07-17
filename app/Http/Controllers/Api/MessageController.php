<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Message;
use App\Services\Memory\MemoryEngine;
use App\Services\WhatsApp\WhatsAppExportParser;
use App\Services\WhatsApp\WhatsAppGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(
        protected WhatsAppGatewayInterface $gateway,
        protected MemoryEngine $memoryEngine,
        protected WhatsAppExportParser $exportParser
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
     * Import a WhatsApp "Export Chat" (.txt, without media) file as historical messages for
     * this contact, then re-learn the contact's style profile and memories from it — this is
     * how the AI learns to sound like the account owner rather than a generic assistant, since
     * the owner's own WhatsApp replies never otherwise reach this app (Fonnte's webhook only
     * forwards inbound messages, not what the owner types from their own phone).
     */
    public function import(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeOwnership($request, $contact);

        $validated = $request->validate([
            'file'       => 'required|file|max:10240', // 10MB, plenty for a text-only export
            'owner_name' => 'required|string|max:255',
        ]);

        abort_unless(
            strtolower($validated['file']->getClientOriginalExtension()) === 'txt',
            422,
            'Expected a WhatsApp "Export Chat" .txt file.'
        );

        $raw = file_get_contents($validated['file']->getRealPath());
        $parsed = $this->exportParser->parse($raw, $validated['owner_name']);

        if (empty($parsed)) {
            return response()->json(['message' => 'No messages could be parsed from this file.'], 422);
        }

        $now = now()->toDateTimeString();

        $rows = array_map(function (array $m) use ($request, $contact, $now) {
            $timestamp = $m['timestamp']?->toDateTimeString();

            return [
                'user_id'    => $request->user()->id,
                'contact_id' => $contact->id,
                'direction'  => $m['direction'],
                'content'    => $m['content'],
                'sent_at'    => $timestamp,
                'created_at' => $timestamp ?? $now,
                'updated_at' => $now,
            ];
        }, $parsed);

        Message::insert($rows);

        // Re-learn from the imported history right away — most recent slice, so the profile
        // reflects current style rather than being diluted by years-old messages, and so the
        // AI prompt used for analysis stays a reasonable size regardless of import length.
        $recent = $contact->messages()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->sortBy('created_at');

        $conversation = $recent
            ->map(fn (Message $m) => ($m->direction === 'in' ? $contact->name : 'Me') . ': ' . $m->content)
            ->implode("\n");

        $memories     = $this->memoryEngine->analyzeAndStore($request->user(), $contact, $conversation);
        $styleProfile = $this->memoryEngine->buildOrUpdateStyleProfile($request->user(), $contact, $conversation);

        return response()->json([
            'imported_count' => count($rows),
            'memories'       => $memories,
            'style_profile'  => $styleProfile,
        ], 201);
    }

    /**
     * Abort with 404 if the contact does not belong to the authenticated user.
     */
    protected function authorizeOwnership(Request $request, Contact $contact): void
    {
        abort_unless($contact->user_id === $request->user()->id, 404);
    }
}
