<?php

namespace App\Services\AI;

interface AIProviderInterface
{
    /**
     * Extract key memories/facts from a conversation text.
     *
     * @param string $conversation Raw conversation text
     * @param array  $context      Additional context (contact name, relationship, etc.)
     * @return array List of extracted memories: [['type'=>..., 'content'=>..., 'importance'=>...]]
     */
    public function extractMemories(string $conversation, array $context = []): array;

    /**
     * Analyze communication style from a conversation.
     *
     * @param string $conversation Raw conversation text
     * @return array Style profile data
     */
    public function analyzeStyle(string $conversation): array;

    /**
     * Generate a suggested reply message.
     *
     * @param string $conversation  Recent conversation context
     * @param array  $styleProfile  Communication style profile
     * @param array  $memories      Relevant memories for context
     * @param string $instruction   Optional extra instruction (e.g. "be more formal")
     * @return string Generated reply text
     */
    public function generateReply(
        string $conversation,
        array $styleProfile = [],
        array $memories = [],
        string $instruction = ''
    ): string;

    /**
     * Decide whether an unattended auto-reply is warranted, and generate it if so, in one call.
     *
     * @param string $conversation       Recent conversation context
     * @param array  $styleProfile       Communication style profile
     * @param array  $memories           Relevant memories for context
     * @param string $guardInstructions  Free-text description of topics that must never be
     *                                   auto-replied to unattended (e.g. money/approval requests)
     * @return array{needs_reply: bool, reply: string, withhold_for_owner: bool}
     */
    public function generateAutoReply(
        string $conversation,
        array $styleProfile = [],
        array $memories = [],
        string $guardInstructions = ''
    ): array;

    /**
     * Generic chat completion.
     *
     * @param string $systemPrompt System prompt
     * @param string $userMessage  User message
     * @return string AI response
     */
    public function chat(string $systemPrompt, string $userMessage): string;
}
