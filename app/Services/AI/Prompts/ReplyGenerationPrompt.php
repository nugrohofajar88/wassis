<?php

namespace App\Services\AI\Prompts;

class ReplyGenerationPrompt
{
    public static function system(array $styleProfile = [], array $memories = []): string
    {
        $formality   = $styleProfile['formality_level'] ?? 3;
        $tone        = $styleProfile['preferred_tone'] ?? 'neutral';
        $usesEmoji   = isset($styleProfile['uses_emoji']) && $styleProfile['uses_emoji'] ? 'yes' : 'no';
        $language    = $styleProfile['typical_language'] ?? 'Indonesian';
        $styleSummary = $styleProfile['summary'] ?? '';

        $memoryContext = '';
        if (! empty($memories)) {
            $memoryContext = "\n\nRelevant memories about this person:\n";
            foreach ($memories as $memory) {
                $memoryContext .= "- {$memory}\n";
            }
        }

        return <<<PROMPT
You are a personal WhatsApp reply assistant. Help the user craft a natural, context-aware reply.

Communication style profile of the contact:
- Formality level: {$formality}/5
- Preferred tone: {$tone}
- Uses emoji: {$usesEmoji}
- Typical language: {$language}
- Style summary: {$styleSummary}
{$memoryContext}

Instructions:
1. Write a reply that matches the contact's communication style.
2. Use the same language as the contact (Indonesian/English/etc).
3. Be natural and human — avoid sounding like a bot.
4. Keep it concise unless the context requires a longer response.
5. Return ONLY the reply text, no explanation.
PROMPT;
    }

    public static function user(string $conversation, string $instruction = ''): string
    {
        $extra = $instruction ? "\n\nExtra instruction: {$instruction}" : '';
        return "Here is the recent conversation:\n\n{$conversation}{$extra}\n\nWrite a suitable reply:";
    }

    /**
     * Variant for unattended auto-reply: the AI must also judge whether the conversation
     * has reached a natural stopping point (any language/slang/dialect) before replying,
     * so the bot doesn't keep talking once the other person is done.
     */
    public static function systemForAutoReply(array $styleProfile = [], array $memories = []): string
    {
        $base = self::system($styleProfile, $memories);

        return <<<PROMPT
{$base}

Additional instructions for unattended auto-reply mode:
6. Before writing a reply, judge whether this conversation has reached a natural stopping point — the other person is signing off, acknowledging, or otherwise done with this exchange for now (in ANY language, dialect, or slang — e.g. Indonesian regional closings like "suwun", "syukron", "aman bro", or a bare "👍"). Judge by meaning and conversational flow, not fixed keywords.
7. If it has NOT reached a stopping point (the topic is still open, a question was asked, something is still being discussed/negotiated), a reply is needed.
8. Return ONLY a valid JSON object, no explanation outside it:
{"needs_reply": true or false, "reply": "the reply text, or empty string if needs_reply is false"}
PROMPT;
    }
}
