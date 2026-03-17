<?php

namespace Tests\Unit\AI\Rag;

use App\Services\AI\Rag\TextChunkerService;
use PHPUnit\Framework\TestCase;

class TextChunkerServiceTest extends TestCase
{
    private TextChunkerService $chunker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunker = new TextChunkerService;
    }

    public function test_empty_text_returns_empty_array(): void
    {
        $this->assertEmpty($this->chunker->chunk(''));
        $this->assertEmpty($this->chunker->chunk('   '));
    }

    public function test_short_text_returns_single_chunk(): void
    {
        $text = 'This is a short text.';
        $chunks = $this->chunker->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertEquals($text, $chunks[0]['text']);
        $this->assertEquals(0, $chunks[0]['index']);
        $this->assertGreaterThan(0, $chunks[0]['tokens']);
    }

    public function test_long_text_produces_multiple_chunks(): void
    {
        // Create text that's > 500 tokens (~2000 chars)
        $paragraphs = [];
        for ($i = 0; $i < 20; $i++) {
            $paragraphs[] = "هذا نص تجريبي طويل يحتوي على معلومات كثيرة عن المشاريع العقارية في المملكة العربية السعودية. يتضمن تفاصيل عن الأسعار والمواقع والخدمات المتاحة.";
        }
        $text = implode("\n\n", $paragraphs);

        $chunks = $this->chunker->chunk($text, 200, 30);

        $this->assertGreaterThan(1, count($chunks));

        // Check indices are sequential
        for ($i = 0; $i < count($chunks); $i++) {
            $this->assertEquals($i, $chunks[$i]['index']);
        }
    }

    public function test_chunk_respects_max_tokens(): void
    {
        $paragraphs = [];
        for ($i = 0; $i < 20; $i++) {
            $paragraphs[] = str_repeat('Word ', 100);
        }
        $text = implode("\n\n", $paragraphs);

        $chunks = $this->chunker->chunk($text, 100, 10);

        foreach ($chunks as $chunk) {
            // Allow some tolerance (~20%) since token estimation is approximate
            $this->assertLessThanOrEqual(150, $chunk['tokens'], "Chunk exceeds max tokens: {$chunk['tokens']}");
        }
    }

    public function test_estimate_tokens(): void
    {
        $text = 'Hello world'; // 11 chars → ~3 tokens
        $tokens = $this->chunker->estimateTokens($text);

        $this->assertGreaterThan(0, $tokens);
        $this->assertLessThan(20, $tokens);
    }

    public function test_arabic_text_chunking(): void
    {
        $text = "الفقرة الأولى عن المبيعات. تحتوي على معلومات مهمة.\n\n"
            . "الفقرة الثانية عن التسويق. تشرح استراتيجيات الحملات.\n\n"
            . "الفقرة الثالثة عن الموارد البشرية. تتحدث عن التوظيف.";

        $chunks = $this->chunker->chunk($text, 500);

        $this->assertGreaterThanOrEqual(1, count($chunks));
        $this->assertNotEmpty($chunks[0]['text']);
    }

    public function test_chunks_have_correct_structure(): void
    {
        $text = str_repeat("A paragraph of text. ", 50) . "\n\n" . str_repeat("Another paragraph. ", 50);

        $chunks = $this->chunker->chunk($text, 100, 20);

        foreach ($chunks as $chunk) {
            $this->assertArrayHasKey('text', $chunk);
            $this->assertArrayHasKey('index', $chunk);
            $this->assertArrayHasKey('tokens', $chunk);
            $this->assertIsString($chunk['text']);
            $this->assertIsInt($chunk['index']);
            $this->assertIsInt($chunk['tokens']);
        }
    }
}
