<?php

namespace App\Services\AI\Rag;

class TextChunkerService
{
    /**
     * Split text into overlapping chunks suitable for embedding.
     *
     * @param  int  $maxTokens  Approximate max tokens per chunk.
     * @param  int  $overlapTokens  Approximate token overlap between consecutive chunks.
     * @return array<int, array{text: string, index: int, tokens: int}>
     */
    public function chunk(string $text, int $maxTokens = 500, int $overlapTokens = 50): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $maxChars = $maxTokens * 4; // ~4 chars per token estimate
        $overlapChars = $overlapTokens * 4;

        // If text fits in a single chunk, return as is
        if ($this->estimateTokens($text) <= $maxTokens) {
            return [
                [
                    'text' => $text,
                    'index' => 0,
                    'tokens' => $this->estimateTokens($text),
                ],
            ];
        }

        // Split into paragraphs first
        $paragraphs = preg_split('/\n\s*\n/', $text);
        $paragraphs = array_filter($paragraphs, fn ($p) => trim($p) !== '');
        $paragraphs = array_values($paragraphs);

        $chunks = [];
        $currentText = '';
        $chunkIndex = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            // If a single paragraph is too large, split it by sentences
            if ($this->estimateTokens($paragraph) > $maxTokens) {
                // Flush current buffer first
                if ($currentText !== '') {
                    $chunks[] = $this->makeChunk($currentText, $chunkIndex++);
                    $currentText = $this->getOverlapText($currentText, $overlapChars);
                }

                $sentenceChunks = $this->chunkBySentences($paragraph, $maxChars, $overlapChars);
                foreach ($sentenceChunks as $sc) {
                    $chunks[] = $this->makeChunk($sc, $chunkIndex++);
                }
                $currentText = $this->getOverlapText($sentenceChunks[count($sentenceChunks) - 1] ?? '', $overlapChars);

                continue;
            }

            $combined = $currentText !== '' ? $currentText . "\n\n" . $paragraph : $paragraph;

            if ($this->estimateTokens($combined) > $maxTokens) {
                // Flush current chunk and start new one with overlap
                $chunks[] = $this->makeChunk($currentText, $chunkIndex++);
                $overlap = $this->getOverlapText($currentText, $overlapChars);
                $currentText = $overlap !== '' ? $overlap . "\n\n" . $paragraph : $paragraph;
            } else {
                $currentText = $combined;
            }
        }

        // Flush remaining text
        if (trim($currentText) !== '') {
            $chunks[] = $this->makeChunk($currentText, $chunkIndex);
        }

        return $chunks;
    }

    /**
     * Approximate token count for text. ~4 chars per token for mixed Arabic/English.
     */
    public function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Split a long paragraph into sentence-level chunks.
     *
     * @return array<string>
     */
    private function chunkBySentences(string $text, int $maxChars, int $overlapChars): array
    {
        // Split by sentence boundaries (., !, ?, Arabic period)
        $sentences = preg_split('/(?<=[.!?؟。])\s+/', $text);
        $sentences = array_filter($sentences, fn ($s) => trim($s) !== '');
        $sentences = array_values($sentences);

        if (empty($sentences)) {
            // Fall back to word-level splitting
            return $this->chunkByWords($text, $maxChars, $overlapChars);
        }

        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if (mb_strlen($sentence) > $maxChars) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = mb_substr($current, -$overlapChars);
                }
                $wordChunks = $this->chunkByWords($sentence, $maxChars, $overlapChars);
                foreach ($wordChunks as $wc) {
                    $chunks[] = $wc;
                }
                $current = mb_substr($wordChunks[count($wordChunks) - 1] ?? '', -$overlapChars);

                continue;
            }

            $combined = $current !== '' ? $current . ' ' . $sentence : $sentence;

            if (mb_strlen($combined) > $maxChars) {
                $chunks[] = $current;
                $overlap = mb_substr($current, -$overlapChars);
                $current = $overlap !== '' ? $overlap . ' ' . $sentence : $sentence;
            } else {
                $current = $combined;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Split text by words as a last resort.
     *
     * @return array<string>
     */
    private function chunkByWords(string $text, int $maxChars, int $overlapChars): array
    {
        $words = preg_split('/\s+/', $text);
        $words = array_filter($words, fn ($w) => $w !== '');

        $chunks = [];
        $current = '';

        foreach ($words as $word) {
            $combined = $current !== '' ? $current . ' ' . $word : $word;

            if (mb_strlen($combined) > $maxChars && $current !== '') {
                $chunks[] = $current;
                $overlap = mb_substr($current, -$overlapChars);
                $current = $overlap !== '' ? $overlap . ' ' . $word : $word;
            } else {
                $current = $combined;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Get overlap text from the end of a string.
     */
    private function getOverlapText(string $text, int $overlapChars): string
    {
        if ($overlapChars <= 0 || mb_strlen($text) <= $overlapChars) {
            return '';
        }

        $overlap = mb_substr($text, -$overlapChars);

        // Try to break at a word boundary
        $spacePos = mb_strpos($overlap, ' ');
        if ($spacePos !== false && $spacePos < mb_strlen($overlap) / 2) {
            $overlap = mb_substr($overlap, $spacePos + 1);
        }

        return trim($overlap);
    }

    /**
     * Create a chunk array.
     *
     * @return array{text: string, index: int, tokens: int}
     */
    private function makeChunk(string $text, int $index): array
    {
        $text = trim($text);

        return [
            'text' => $text,
            'index' => $index,
            'tokens' => $this->estimateTokens($text),
        ];
    }
}
