<?php

namespace App\Services\Memory;

use App\Models\Contact;
use App\Models\Memory;
use App\Models\StyleProfile;
use App\Models\User;
use App\Services\AI\AIProviderInterface;
use Illuminate\Support\Facades\Log;

class MemoryEngine
{
    public function __construct(
        protected AIProviderInterface $ai
    ) {}

    /**
     * Store a single memory for a user (and optionally a contact).
     */
    public function remember(
        User $user,
        ?Contact $contact,
        string $type,
        string $content,
        int $importance = 5,
        ?\DateTime $expiresAt = null
    ): Memory {
        return Memory::create([
            'user_id'          => $user->id,
            'contact_id'       => $contact?->id,
            'type'             => $type,
            'content'          => $content,
            'importance_score' => max(1, min(10, $importance)),
            'expires_at'       => $expiresAt,
        ]);
    }

    /**
     * Retrieve active memories for a user/contact, formatted as strings.
     *
     * @param  string[]  $types Filter by types (default: all)
     * @return string[]
     */
    public function recall(User $user, ?Contact $contact = null, array $types = []): array
    {
        $query = Memory::query()
            ->where('user_id', $user->id)
            ->active()
            ->orderByDesc('importance_score');

        if ($contact) {
            $query->where(function ($q) use ($contact) {
                $q->where('contact_id', $contact->id)
                  ->orWhereNull('contact_id');
            });
        }

        if (! empty($types)) {
            $query->whereIn('type', $types);
        }

        return $query->pluck('content')->toArray();
    }

    /**
     * Delete a specific memory.
     */
    public function forget(Memory $memory): void
    {
        $memory->delete();
    }

    /**
     * Delete all expired short_term memories for a user.
     */
    public function purgeExpired(User $user): int
    {
        return Memory::where('user_id', $user->id)
            ->where('type', 'short_term')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Analyze a conversation via AI and store extracted memories.
     *
     * @param  string  $conversation  Formatted conversation text
     */
    public function analyzeAndStore(User $user, Contact $contact, string $conversation): array
    {
        $context = [
            'contact_name'      => $contact->name,
            'relationship_type' => $contact->relationship_type,
        ];

        try {
            $extracted = $this->ai->extractMemories($conversation, $context);
        } catch (\Exception $e) {
            Log::error('MemoryEngine: AI extraction failed', ['error' => $e->getMessage()]);
            return [];
        }

        $stored = [];

        foreach ($extracted as $item) {
            $type       = $item['type'] ?? 'long_term';
            $content    = $item['content'] ?? null;
            $importance = (int) ($item['importance'] ?? 5);

            if (! $content || ! in_array($type, ['short_term', 'long_term', 'relationship', 'style'])) {
                continue;
            }

            // Avoid storing duplicate content
            $exists = Memory::where('user_id', $user->id)
                ->where('contact_id', $contact->id)
                ->where('content', $content)
                ->exists();

            if ($exists) {
                continue;
            }

            // short_term expires in 7 days
            $expiresAt = $type === 'short_term' ? now()->addDays(7) : null;

            $stored[] = $this->remember($user, $contact, $type, $content, $importance, $expiresAt);
        }

        return $stored;
    }

    /**
     * Build or update the style profile for a contact via AI.
     */
    public function buildOrUpdateStyleProfile(User $user, Contact $contact, string $conversation): ?StyleProfile
    {
        try {
            $styleData = $this->ai->analyzeStyle($conversation);
        } catch (\Exception $e) {
            Log::error('MemoryEngine: Style analysis failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (empty($styleData)) {
            return null;
        }

        $profile = StyleProfile::updateOrCreate(
            ['user_id' => $user->id, 'contact_id' => $contact->id],
            [
                'formality_level'  => $styleData['formality_level'] ?? 3,
                'preferred_tone'   => $styleData['preferred_tone'] ?? null,
                'uses_emoji'       => $styleData['uses_emoji'] ?? true,
                'typical_language' => $styleData['typical_language'] ?? null,
                'summary'          => $styleData['summary'] ?? null,
                'last_analyzed_at' => now(),
            ]
        );

        return $profile;
    }

    /**
     * Suggest a reply for the given conversation, using memories and style profile.
     */
    public function suggestReply(
        User $user,
        Contact $contact,
        string $conversation,
        string $instruction = ''
    ): string {
        $memories  = $this->resolveMemories($user, $contact);
        $styleData = $this->resolveStyleData($user, $contact);

        try {
            return $this->ai->generateReply($conversation, $styleData, $memories, $instruction);
        } catch (\Exception $e) {
            Log::error('MemoryEngine: Reply generation failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Decide whether an unattended auto-reply is warranted for the given conversation, and
     * generate it if so. Unlike suggestReply(), this also judges whether the conversation has
     * reached a natural stopping point, so a bot doesn't keep replying once the other person
     * is done — and whether it touches a topic the owner marked off-limits for unattended
     * replies (see `sensitive_topics_guard` Setting), in which case it stays silent.
     *
     * @return array{needs_reply: bool, reply: string, withhold_for_owner: bool}
     */
    public function suggestAutoReply(User $user, Contact $contact, string $conversation): array
    {
        $memories  = $this->resolveMemories($user, $contact);
        $styleData = $this->resolveStyleData($user, $contact);
        $guard     = $user->getSetting('sensitive_topics_guard', '');

        try {
            return $this->ai->generateAutoReply($conversation, $styleData, $memories, $guard);
        } catch (\Exception $e) {
            Log::error('MemoryEngine: Auto-reply decision failed', ['error' => $e->getMessage()]);
            return ['needs_reply' => false, 'reply' => '', 'withhold_for_owner' => false];
        }
    }

    /**
     * Resolve the style data to prompt with: the contact's own analyzed StyleProfile if one
     * exists, otherwise the user's global `default_persona` setting as a coarse fallback so a
     * brand-new contact (no conversation history yet) doesn't get a completely generic reply
     * while their own style profile hasn't been learned yet.
     */
    protected function resolveStyleData(User $user, Contact $contact): array
    {
        $styleProfile = StyleProfile::where('user_id', $user->id)
            ->where('contact_id', $contact->id)
            ->first();

        if ($styleProfile) {
            return $styleProfile->toArray();
        }

        $defaultPersona = $user->getSetting('default_persona', '');

        return $defaultPersona ? ['summary' => $defaultPersona] : [];
    }

    /**
     * Resolve the memory list to prompt with: stored Memory rows plus the contact's manually
     * written `notes` field (if any), surfaced as the first entry so the owner's own context
     * about this person (e.g. "adik ipar, suka bercanda") shapes replies the same way an
     * AI-extracted memory would, distinguishable by its label prefix.
     */
    protected function resolveMemories(User $user, Contact $contact): array
    {
        $memories = $this->recall($user, $contact);

        if ($contact->notes) {
            array_unshift($memories, "Catatan pemilik akun tentang kontak ini: {$contact->notes}");
        }

        return $memories;
    }
}
