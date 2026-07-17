<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Message;
use App\Services\Memory\MemoryEngine;
use App\Services\WhatsApp\WhatsAppGatewayInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Debounced auto-reply: dispatched with a delay after an inbound WhatsApp message. If a newer
 * inbound message from the same contact has arrived by the time this runs, it bails — the job
 * dispatched for that newer message will handle the (now more complete) conversation instead.
 */
class ProcessAutoReply implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $contactId,
        protected int $triggeringMessageId,
        protected string $triggeringMessageCreatedAt
    ) {}

    public function handle(WhatsAppGatewayInterface $gateway, MemoryEngine $memoryEngine): void
    {
        $contact = Contact::with('user')->find($this->contactId);

        if (! $contact || ! $contact->user) {
            return;
        }

        $user = $contact->user;

        // Re-check both toggles at execution time — state may have changed during the debounce delay.
        if (! $contact->ai_enabled || ! $user->getSetting('auto_reply_enabled', false)) {
            return;
        }

        $newerMessageExists = Message::where('contact_id', $contact->id)
            ->where('direction', 'in')
            ->where('created_at', '>', $this->triggeringMessageCreatedAt)
            ->exists();

        if ($newerMessageExists) {
            // Superseded — the job for that newer message will pick up the full, combined conversation.
            return;
        }

        $dailyLimit = (int) config('whatsapp.auto_reply_daily_limit', 25);
        $sentToday  = Message::where('contact_id', $contact->id)
            ->where('direction', 'out')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($sentToday >= $dailyLimit) {
            $contact->update(['ai_enabled' => false]);
            Log::warning('ProcessAutoReply: daily auto-reply limit reached, disabling ai_enabled for contact', [
                'contact_id' => $contact->id,
                'sent_today' => $sentToday,
                'limit'      => $dailyLimit,
            ]);
            return;
        }

        $recent = $contact->messages()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->sortBy('created_at');

        $conversation = $recent
            ->map(fn (Message $m) => ($m->direction === 'in' ? $contact->name : 'Me') . ': ' . $m->content)
            ->implode("\n");

        $decision = $memoryEngine->suggestAutoReply($user, $contact, $conversation);

        if ($decision['withhold_for_owner'] ?? false) {
            Log::info('ProcessAutoReply: withheld a reply for owner review (sensitive topic)', [
                'contact_id' => $contact->id,
            ]);
            return;
        }

        if (! $decision['needs_reply'] || ! $decision['reply']) {
            return;
        }

        $response = $gateway->sendMessage($contact->phone, $decision['reply']);
        $success  = (bool) ($response['status'] ?? false);

        $contact->messages()->create([
            'user_id'            => $user->id,
            'direction'          => 'out',
            'content'            => $decision['reply'],
            'status'             => $success ? 'sent' : 'failed',
            'gateway_message_id' => $response['id'] ?? null,
            'sent_at'            => $success ? now() : null,
        ]);
    }
}
