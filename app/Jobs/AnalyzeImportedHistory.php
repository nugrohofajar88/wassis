<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Message;
use App\Services\Memory\MemoryEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs memory extraction + style profile analysis on a contact's most recent messages after a
 * WhatsApp chat import. Dispatched (not called inline) because a large import easily has enough
 * messages that two sequential AI calls in the same HTTP request risk hitting the host's request
 * timeout with no response at all — see AGENTS.md ("Import AI analysis moved off the request").
 */
class AnalyzeImportedHistory implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $userId,
        protected int $contactId
    ) {}

    public function handle(MemoryEngine $memoryEngine): void
    {
        $contact = Contact::with('user')->find($this->contactId);

        if (! $contact || ! $contact->user || $contact->user->id !== $this->userId) {
            return;
        }

        $recent = $contact->messages()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->sortBy('created_at');

        if ($recent->isEmpty()) {
            return;
        }

        $conversation = $recent
            ->map(fn (Message $m) => ($m->direction === 'in' ? $contact->name : 'Me') . ': ' . $m->content)
            ->implode("\n");

        $memoryEngine->analyzeAndStore($contact->user, $contact, $conversation);
        $memoryEngine->buildOrUpdateStyleProfile($contact->user, $contact, $conversation);
    }
}
