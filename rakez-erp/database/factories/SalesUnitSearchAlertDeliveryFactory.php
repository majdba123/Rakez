<?php

namespace Database\Factories;

use App\Models\ContractUnit;
use App\Models\SalesUnitSearchAlert;
use App\Models\SalesUnitSearchAlertDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesUnitSearchAlertDeliveryFactory extends Factory
{
    protected $model = SalesUnitSearchAlertDelivery::class;

    public function definition(): array
    {
        return [
            'sales_unit_search_alert_id' => SalesUnitSearchAlert::factory(),
            'contract_unit_id' => ContractUnit::factory(),
            'client_mobile' => '+9665'.$this->faker->numerify('########'),
            'delivery_channel' => SalesUnitSearchAlertDelivery::CHANNEL_SMS,
            'status' => SalesUnitSearchAlertDelivery::STATUS_PENDING,
        ];
    }
}
