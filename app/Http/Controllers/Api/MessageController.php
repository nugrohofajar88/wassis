<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeContactHistory;
use App\Models\Contact;
use App\Models\Message;
use App\Services\Memory\MemoryEngine;
use App\Services\WhatsApp\WhatsAppExportParser;
use App\Services\WhatsApp\WhatsAppGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     * this contact. Saving the messages happens inline (fast, even for thousands of rows via
     * chunked insert); re-learning the contact's style profile and memories from that history
     * is dispatched to a queued job instead of running inline — two sequential AI calls in the
     * same request risk exceeding the host's request timeout for a large import, returning no
     * response at all. This whole feature exists because the owner's own WhatsApp replies never
     * otherwise reach this app (Fonnte's webhook only forwards inbound messages, not what the
     * owner types from their own phone) — without it, the AI only ever learns from its own
     * previously auto-generated replies.
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

        [$insertedCount, $skippedCount] = $this->insertResiliently($rows);

        if ($insertedCount === 0) {
            return response()->json(['message' => 'All rows failed to import. Check storage/logs for details.'], 422);
        }

        // Queued, not called inline — see the method docblock. Picked up by the same
        // cron-driven queue processing as auto-reply (AGENTS.md: "Production deployment").
        AnalyzeContactHistory::dispatch($request->user()->id, $contact->id);

        return response()->json([
            'imported_count' => $insertedCount,
            'skipped_count'  => $skippedCount,
            'analysis'       => 'queued',
        ], 201);
    }

    /**
     * Delete one or more messages belonging to this contact. Only deletes the app's own record
     * of the message — WhatsApp itself has no API to un-send/delete a message on the other
     * person's device, so this is purely local cleanup (e.g. removing test/junk messages so
     * they stop polluting the conversation history the AI reads for context).
     */
    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeOwnership($request, $contact);

        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $deleted = $contact->messages()->whereIn('id', $validated['ids'])->delete();

        return response()->json(['deleted_count' => $deleted]);
    }

    /**
     * Bulk-insert in chunks, falling back to one-row-at-a-time within any chunk that fails —
     * a single bad row (e.g. mangled encoding the parser's sanitizer didn't catch) shouldn't
     * take an entire multi-thousand-message import down with it.
     *
     * @return array{0: int, 1: int} [insertedCount, skippedCount]
     */
    protected function insertResiliently(array $rows): array
    {
        $inserted = 0;
        $skipped  = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            try {
                Message::insert($chunk);
                $inserted += count($chunk);
                continue;
            } catch (\Throwable $e) {
                // Fall through to per-row insertion below.
            }

            foreach ($chunk as $row) {
                try {
                    Message::insert([$row]);
                    $inserted++;
                } catch (\Throwable $rowException) {
                    $skipped++;
                    Log::warning('WhatsApp import: skipped a row that failed to insert', [
                        'error'            => $rowException->getMessage(),
                        'content_preview'  => substr($row['content'], 0, 100),
                    ]);
                }
            }
        }

        return [$inserted, $skipped];
    }

    /**
     * Abort with 404 if the contact does not belong to the authenticated user.
     */
    protected function authorizeOwnership(Request $request, Contact $contact): void
    {
        abort_unless($contact->user_id === $request->user()->id, 404);
    }
}
