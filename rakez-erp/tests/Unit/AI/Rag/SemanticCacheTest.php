<?php

namespace Tests\Unit\AI\Rag;

use App\Services\AI\Rag\SemanticCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SemanticCacheTest extends TestCase
{
    private SemanticCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new SemanticCache;
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->cache->get('nonexistent query'));
    }

    public function test_put_and_get(): void
    {
        $data = ['matches' => [['content' => 'test']]];
        $this->cache->put('test query', $data);

        $result = $this->cache->get('test query');

        $this->assertEquals($data, $result);
    }

    public function test_has(): void
    {
        $this->assertFalse($this->cache->has('test'));

        $this->cache->put('test', ['data' => true]);

        $this->assertTrue($this->cache->has('test'));
    }

    public function test_forget(): void
    {
        $this->cache->put('test', ['data' => true]);
        $this->assertTrue($this->cache->has('test'));

        $this->cache->forget('test');
        $this->assertFalse($this->cache->has('test'));
    }

    public function test_flush_invalidates_all_entries(): void
    {
        $this->cache->put('query1', ['data' => 1]);
        $this->cache->put('query2', ['data' => 2]);

        $this->cache->flush();

        $this->assertNull($this->cache->get('query1'));
        $this->assertNull($this->cache->get('query2'));
    }

    public function test_case_insensitive_queries(): void
    {
        $this->cache->put('Test Query', ['data' => true]);

        // Same query in lowercase should match
        $result = $this->cache->get('test query');
        $this->assertNotNull($result);
    }
}
