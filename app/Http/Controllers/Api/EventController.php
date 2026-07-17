<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * List the authenticated user's events. Defaults to upcoming events only.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->events();

        if ($request->boolean('upcoming', true)) {
            $query->upcoming();
        } else {
            $query->orderBy('start_at');
        }

        return response()->json(['events' => $query->get()]);
    }

    /**
     * Create a new event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'  => 'nullable|exists:contacts,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_at'    => 'required|date',
            'end_at'      => 'nullable|date|after_or_equal:start_at',
        ]);

        if (! empty($validated['contact_id'])) {
            $owned = $request->user()->contacts()->whereKey($validated['contact_id'])->exists();
            abort_unless($owned, 404);
        }

        $event = $request->user()->events()->create([
            ...$validated,
            'source' => 'manual',
        ]);

        return response()->json(['event' => $event], 201);
    }

    /**
     * Show a single event.
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorizeOwnership($request, $event);

        return response()->json(['event' => $event]);
    }

    /**
     * Update an event.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorizeOwnership($request, $event);

        $validated = $request->validate([
            'contact_id'  => 'nullable|exists:contacts,id',
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'start_at'    => 'sometimes|required|date',
            'end_at'      => 'nullable|date|after_or_equal:start_at',
        ]);

        if (! empty($validated['contact_id'])) {
            $owned = $request->user()->contacts()->whereKey($validated['contact_id'])->exists();
            abort_unless($owned, 404);
        }

        $event->update($validated);

        return response()->json(['event' => $event]);
    }

    /**
     * Delete an event.
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->authorizeOwnership($request, $event);

        $event->delete();

        return response()->json(['message' => 'Event deleted.']);
    }

    /**
     * Abort with 404 if the event does not belong to the authenticated user.
     */
    protected function authorizeOwnership(Request $request, Event $event): void
    {
        abort_unless($event->user_id === $request->user()->id, 404);
    }
}
