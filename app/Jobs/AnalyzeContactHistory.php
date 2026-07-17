<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Message;
use App\Services\Memory\MemoryEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs memory extraction + style profile analysis on a contact's most recent messages. Two call
 * sites: after a WhatsApp chat import (MessageController::import), and periodically as
 * conversation accumulates (WebhookController::fonnte, every `auto_reanalyze_message_interval`
 * messages) so the style profile keeps improving without the owner remembering to trigger it
 * manually. Always dispatched (never called inline) — even the periodic trigger runs two
 * sequential AI calls, and doing that inside the webhook request risks the same request-timeout
 * problem as the original import case — see AGENTS.md ("Import AI analysis moved off the request").
 */
class AnalyzeContactHistory implements ShouldQueue
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
