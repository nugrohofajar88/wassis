<?php

namespace App\Services\WhatsApp;

use Carbon\Carbon;

/**
 * Parses a WhatsApp "Export Chat" (.txt, without media) file into structured messages.
 *
 * WhatsApp's export format varies by platform/locale — this supports the two dominant
 * shapes (iOS-style bracketed, Android-style dash-separated) and treats any line that
 * doesn't match either as a continuation of the previous message (WhatsApp exports
 * multi-line messages as raw newlines with no per-line prefix).
 */
class WhatsAppExportParser
{
    protected const PATTERNS = [
        // [17/07/26, 14:23:01] Budi: halo bro
        '/^\[(\d{1,2}\/\d{1,2}\/\d{2,4},\s*\d{1,2}:\d{2}(?::\d{2})?(?:\s?[AP]M)?)\]\s(.+?):\s(.*)$/iu',
        // 17/07/26, 14:23 - Budi: halo bro
        '/^(\d{1,2}\/\d{1,2}\/\d{2,4},\s*\d{1,2}:\d{2}(?:\s?[AP]M)?)\s[-–]\s(.+?):\s(.*)$/iu',
    ];

    protected const SKIP_CONTENT = [
        'Messages and calls are end-to-end encrypted',
        'image omitted',
        'video omitted',
        'audio omitted',
        'sticker omitted',
        'document omitted',
        'GIF omitted',
        '<Media omitted>',
        'This message was deleted',
        'You deleted this message',
    ];

    /**
     * @param  string  $ownerName  Exact sender name (as it appears in the export) representing
     *                             the account owner. Any other sender is treated as the contact.
     * @return array<int, array{sender: string, content: string, timestamp: ?Carbon, direction: string}>
     */
    public function parse(string $rawText, string $ownerName): array
    {
        $lines    = preg_split('/\r\n|\r|\n/', $rawText);
        $messages = [];

        foreach ($lines as $line) {
            $line = trim($line, "\xEF\xBB\xBF\x{200E}\x{200F} \t"); // strip BOM/LRM/RLM + whitespace

            if ($line === '') {
                continue;
            }

            $matched = false;

            foreach (self::PATTERNS as $pattern) {
                if (preg_match($pattern, $line, $m)) {
                    $sender  = trim($m[2]);
                    $content = trim($m[3]);

                    if ($content === '' || $this->isSkippable($content)) {
                        $matched = true;
                        break;
                    }

                    $messages[] = [
                        'sender'    => $sender,
                        'content'   => $content,
                        'timestamp' => $this->parseTimestamp($m[1]),
                        'direction' => $sender === $ownerName ? 'out' : 'in',
                    ];
                    $matched = true;
                    break;
                }
            }

            if (! $matched && ! empty($messages)) {
                // Continuation line of a multi-line message.
                $messages[count($messages) - 1]['content'] .= "\n" . $line;
            }
        }

        return $messages;
    }

    protected function isSkippable(string $content): bool
    {
        foreach (self::SKIP_CONTENT as $needle) {
            if (str_contains($content, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function parseTimestamp(string $raw): ?Carbon
    {
        // WhatsApp doesn't zero-pad day/month ("1/1/26", not "01/01/26") — use 'j'/'n', not
        // 'd'/'m', or single-digit dates fail to parse. Day/month order is also ambiguous
        // (WhatsApp doesn't disambiguate); try the common orders and fall back to null rather
        // than guessing wrong — callers don't depend on exact historical timestamps.
        foreach (['j/n/y, H:i:s', 'j/n/y, H:i', 'j/n/Y, H:i:s', 'j/n/Y, H:i', 'n/j/y, h:i A', 'n/j/y, h:i:s A'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw);
            } catch (\Exception) {
                continue;
            }
        }

        return null;
    }
}
