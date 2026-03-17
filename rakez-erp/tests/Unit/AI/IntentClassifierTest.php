<?php

namespace Tests\Unit\AI;

use App\Services\AI\CatalogService;
use App\Services\AI\IntentClassifier;
use Mockery;
use PHPUnit\Framework\TestCase;

class IntentClassifierTest extends TestCase
{
    private IntentClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $catalogService = Mockery::mock(CatalogService::class);
        $this->classifier = new IntentClassifier($catalogService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_classifies_arabic_catalog_query(): void
    {
        $this->assertEquals('catalog_query', $this->classifier->classify('وش أقسامي؟')['intent']);
        $this->assertEquals('catalog_query', $this->classifier->classify('صلاحياتي')['intent']);
        $this->assertEquals('catalog_query', $this->classifier->classify('وش أقدر أسوي')['intent']);
        $this->assertEquals('catalog_query', $this->classifier->classify('الأقسام المتاحة')['intent']);
    }

    public function test_classifies_english_catalog_query(): void
    {
        $this->assertEquals('catalog_query', $this->classifier->classify('my sections')['intent']);
        $this->assertEquals('catalog_query', $this->classifier->classify('what can i do')['intent']);
        $this->assertEquals('catalog_query', $this->classifier->classify('list sections')['intent']);
    }

    public function test_classifies_general_queries(): void
    {
        $this->assertEquals('general', $this->classifier->classify('كم عدد الليدات هذا الشهر؟')['intent']);
        $this->assertEquals('general', $this->classifier->classify('احسب لي تمويل عقاري')['intent']);
        $this->assertEquals('general', $this->classifier->classify('hello')['intent']);
    }

    public function test_case_insensitive(): void
    {
        $this->assertEquals('catalog_query', $this->classifier->classify('MY SECTIONS')['intent']);
        $this->assertEquals('catalog_query', $this->classifier->classify('What Can I Do')['intent']);
    }
}
