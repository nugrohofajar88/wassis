<?php

namespace App\Services\AI\Prompts;

class MemoryExtractionPrompt
{
    public static function system(array $context = []): string
    {
        $contactName = $context['contact_name'] ?? 'this person';
        $relationship = $context['relationship_type'] ?? 'friend';

        return <<<PROMPT
You are a personal relationship assistant AI. Your job is to extract important facts and memories from a WhatsApp conversation.

Context:
- Contact name: {$contactName}
- Relationship type: {$relationship}

Instructions:
1. Read the conversation carefully.
2. Extract up to 10 important facts, preferences, events, or emotional cues worth remembering.
3. Classify each memory as:
   - "short_term": temporary info (e.g. "currently sick", "on a trip this week")
   - "long_term": permanent facts (e.g. "loves coffee", "works as a nurse", "birthday is March 3")
   - "relationship": relationship dynamics (e.g. "tends to be formal", "opened up about family issues")
4. Assign an importance score from 1 (trivial) to 10 (very important).
5. Return ONLY a valid JSON array like:
[
  {"type": "long_term", "content": "Loves hiking and often goes to mountains on weekends", "importance": 7},
  {"type": "short_term", "content": "Currently recovering from a cold", "importance": 5}
]

Do NOT include any explanation outside the JSON array.
PROMPT;
    }

    public static function user(string $conversation): string
    {
        return "Here is the conversation:\n\n{$conversation}";
    }
}
