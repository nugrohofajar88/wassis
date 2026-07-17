<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * List the authenticated user's contacts.
     */
    public function index(Request $request): JsonResponse
    {
        $contacts = $request->user()->contacts()
            ->orderBy('name')
            ->get();

        return response()->json(['contacts' => $contacts]);
    }

    /**
     * Create a new contact.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'phone'              => 'required|string|max:20',
            'relationship_type'  => 'in:friend,family,colleague,partner,other',
            'notes'              => 'nullable|string',
            'avatar'             => 'nullable|string',
        ]);

        $contact = $request->user()->contacts()->create($validated);

        // Columns with DB-level defaults (is_active, ai_enabled) aren't hydrated on the
        // in-memory instance after create() unless explicitly passed — refresh so the
        // response reflects the actual stored row instead of nulls.
        return response()->json(['contact' => $contact->fresh()], 201);
    }

    /**
     * Show a single contact.
     */
    public function show(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeOwnership($request, $contact);

        return response()->json(['contact' => $contact]);
    }

    /**
     * Update a contact.
     */
    public function update(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeOwnership($request, $contact);

        $validated = $request->validate([
            'name'               => 'sometimes|required|string|max:255',
            'phone'              => 'sometimes|required|string|max:20',
            'relationship_type'  => 'in:friend,family,colleague,partner,other',
            'notes'              => 'nullable|string',
            'avatar'             => 'nullable|string',
            'is_active'          => 'boolean',
            'ai_enabled'         => 'boolean',
        ]);

        $contact->update($validated);

        return response()->json(['contact' => $contact]);
    }

    /**
     * Delete a contact.
     */
    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeOwnership($request, $contact);

        $contact->delete();

        return response()->json(['message' => 'Contact deleted.']);
    }

    /**
     * Abort with 404 if the contact does not belong to the authenticated user.
     */
    protected function authorizeOwnership(Request $request, Contact $contact): void
    {
        abort_unless($contact->user_id === $request->user()->id, 404);
    }
}
