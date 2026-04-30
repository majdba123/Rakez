<?php

namespace App\Services\Sales;

use App\Models\SalesUnitSearchAlert;

class UnitSearchCriteria
{
    private const PERSISTED_KEYS = [
        'city_id',
        'district_id',
        'project_id',
        'unit_type',
        'floor',
        'min_price',
        'max_price',
        'min_area',
        'max_area',
        'min_bedrooms',
        'max_bedrooms',
        'query_text',
    ];

    private const SEARCH_KEYS = [
        'city_id',
        'district_id',
        'city',
        'district',
        'project_id',
        'status',
        'unit_type',
        'floor',
        'min_price',
        'max_price',
        'min_area',
        'max_area',
        'min_bedrooms',
        'max_bedrooms',
        'q',
        'query_text',
    ];

    public function normalizeForSearch(array $input): array
    {
        $criteria = [];

        foreach (self::SEARCH_KEYS as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== '' && $input[$key] !== null) {
                $criteria[$key] = $input[$key];
            }
        }

        if (! isset($criteria['q']) && isset($criteria['query_text'])) {
            $criteria['q'] = $criteria['query_text'];
        }

        return $criteria;
    }

    public function normalizeForPersistence(array $input): array
    {
        $criteria = [];
        $source = $input;

        if (! isset($source['query_text']) && isset($source['q'])) {
            $source['query_text'] = $source['q'];
        }

        foreach (self::PERSISTED_KEYS as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== '' && $source[$key] !== null) {
                $criteria[$key] = $source[$key];
            }
        }

        return $criteria;
    }

    public function fromAlert(SalesUnitSearchAlert $alert): array
    {
        return $this->normalizeForSearch([
            'city_id' => $alert->city_id,
            'district_id' => $alert->district_id,
            'project_id' => $alert->project_id,
            'unit_type' => $alert->unit_type,
            'floor' => $alert->floor,
            'min_price' => $alert->min_price,
            'max_price' => $alert->max_price,
            'min_area' => $alert->min_area,
            'max_area' => $alert->max_area,
            'min_bedrooms' => $alert->min_bedrooms,
            'max_bedrooms' => $alert->max_bedrooms,
            'query_text' => $alert->query_text,
        ]);
    }
}
