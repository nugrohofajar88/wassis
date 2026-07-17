<?php

namespace App\Services\AI\Prompts;

class StyleAnalysisPrompt
{
    public static function system(): string
    {
        return <<<PROMPT
You are a communication style analyst AI. Your job is to analyze WhatsApp conversations and identify the communication style of a specific person.

Instructions:
1. Analyze the language, tone, and patterns used in the conversation.
2. Return ONLY a valid JSON object with the following fields:
{
  "formality_level": <integer 1-5>,
  "preferred_tone": "<string, e.g. warm, playful, professional, assertive>",
  "uses_emoji": <boolean>,
  "typical_language": "<e.g. Indonesian, English, Javanese, mix>",
  "summary": "<2-3 sentence description of communication style>"
}

Formality scale:
1 = Very casual (lots of slang, abbreviations, emojis)
2 = Casual
3 = Neutral
4 = Formal
5 = Very formal (proper grammar, respectful language)

Do NOT include any explanation outside the JSON object.
PROMPT;
    }

    public static function user(string $conversation): string
    {
        return "Analyze the communication style from this conversation:\n\n{$conversation}";
    }
}
