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

            // Sanitize BEFORE regex matching, not after: the patterns use the /u (Unicode)
            // modifier, and PCRE silently fails to match at all (not just on the bad part) when
            // the subject contains invalid UTF-8 — real exports can contain such bytes (mangled
            // emoji encoding, e.g. a lone 0xC2 lead byte). Sanitizing post-match never even
            // triggers if the match itself already failed, silently dropping the whole line.
            $line = $this->sanitize($line);

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

    /**
     * Real WhatsApp exports can contain byte sequences that aren't valid UTF-8 (mangled
     * encoding of emoji/special characters, e.g. lone 0xC2 bytes) or stray control characters.
     * MySQL rejects invalid UTF-8 outright (error 1366), which — without this — takes down the
     * entire bulk insert for the whole file over a single bad character in one message.
     */
    protected function sanitize(string $text): string
    {
        // iconv with //IGNORE reliably drops byte sequences that aren't valid UTF-8 — more
        // dependable across environments than mb_convert_encoding, whose invalid-sequence
        // handling varies with the mbstring.substitute_character ini setting.
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if ($clean === false || $clean === null) {
            $clean = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        // Strip C0/C1 control characters except tab/newline. No /u modifier here on purpose —
        // these are single-byte ASCII codepoints, and requiring valid UTF-8 to run this would
        // just reintroduce the exact failure mode this method exists to avoid.
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
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
