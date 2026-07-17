<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Memory;
use App\Models\Message;
use App\Services\Memory\MemoryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function __construct(
        protected MemoryEngine $memoryEngine
    ) {}

    /**
     * List memories for the authenticated user, optionally filtered by contact/type.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => 'nullable|exists:contacts,id',
            'type'       => 'nullable|in:short_term,long_term,relationship,style',
        ]);

        $query = Memory::where('user_id', $request->user()->id)->active();

        if (! empty($validated['contact_id'])) {
            $contact = Contact::findOrFail($validated['contact_id']);
            abort_unless($contact->user_id === $request->user()->id, 404);
            $query->where('contact_id', $contact->id);
        }

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $memories = $query->orderByDesc('importance_score')->get();

        return response()->json(['memories' => $memories]);
    }

    /**
     * Manually store a memory.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'  => 'nullable|exists:contacts,id',
            'type'        => 'required|in:short_term,long_term,relationship,style',
            'content'     => 'required|string',
            'importance'  => 'integer|min:1|max:10',
        ]);

        $contact = null;
        if (! empty($validated['contact_id'])) {
            $contact = Contact::findOrFail($validated['contact_id']);
            abort_unless($contact->user_id === $request->user()->id, 404);
        }

        $expiresAt = $validated['type'] === 'short_term' ? now()->addDays(7) : null;

        $memory = $this->memoryEngine->remember(
            $request->user(),
            $contact,
            $validated['type'],
            $validated['content'],
            $validated['importance'] ?? 5,
            $expiresAt
        );

        return response()->json(['memory' => $memory], 201);
    }

    /**
     * Delete a memory.
     */
    public function destroy(Request $request, Memory $memory): JsonResponse
    {
        abort_unless($memory->user_id === $request->user()->id, 404);

        $this->memoryEngine->forget($memory);

        return response()->json(['message' => 'Memory deleted.']);
    }

    /**
     * Run AI extraction over a contact's recent conversation and store the resulting memories,
     * refreshing the style profile at the same time.
     */
    public function analyze(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 404);

        $recent = $contact->messages()
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->sortBy('created_at');

        abort_if($recent->isEmpty(), 422, 'No messages to analyze for this contact.');

        $conversation = $recent
            ->map(fn (Message $m) => ($m->direction === 'in' ? $contact->name : 'Me') . ': ' . $m->content)
            ->implode("\n");

        $stored = $this->memoryEngine->analyzeAndStore($request->user(), $contact, $conversation);
        $styleProfile = $this->memoryEngine->buildOrUpdateStyleProfile($request->user(), $contact, $conversation);

        return response()->json([
            'memories'      => $stored,
            'style_profile' => $styleProfile,
        ]);
    }
}
