<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\SecondPartyData;
use Illuminate\Database\Eloquent\Factories\Factory;

class SecondPartyDataFactory extends Factory
{
    protected $model = SecondPartyData::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'real_estate_papers_url' => 'https://example.com/doc1.pdf',
            'plans_equipment_docs_url' => 'https://example.com/doc2.pdf',
            'project_logo_url' => 'https://example.com/logo.png',
            'prices_units_url' => 'https://example.com/units.pdf',
            'marketing_license_url' => 'https://example.com/license.pdf',
            'advertiser_section_url' => 'https://example.com/advert.pdf',
        ];
    }
}
