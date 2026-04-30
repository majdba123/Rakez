<?php

namespace Tests\Unit\Sales;

use App\Services\Sales\UnitSearchCriteria;
use Tests\TestCase;

class UnitSearchCriteriaTest extends TestCase
{
    public function test_persistence_criteria_strips_pagination_and_sorting(): void
    {
        $criteria = app(UnitSearchCriteria::class)->normalizeForPersistence([
            'city_id' => 1,
            'project_id' => 2,
            'q' => 'A-101',
            'page' => 3,
            'per_page' => 50,
            'sort_by' => 'price',
            'sort_dir' => 'asc',
        ]);

        $this->assertSame([
            'city_id' => 1,
            'project_id' => 2,
            'query_text' => 'A-101',
        ], $criteria);
    }
}
