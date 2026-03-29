<?php

use App\Models\City;
use App\Models\Contract;
use App\Models\District;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('developer_number')->constrained('cities')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('city_id')->constrained('districts')->nullOnDelete();
        });

        if (Schema::hasColumn('contracts', 'city')) {
            foreach (Contract::query()->orderBy('id')->cursor() as $contract) {
                $attrs = $contract->getAttributes();
                $cityName = $attrs['city'] ?? null;
                $districtName = $attrs['district'] ?? null;
                if ($cityName === null || $cityName === '') {
                    continue;
                }
                $city = City::query()->where('name', $cityName)->first();
                if (!$city) {
                    continue;
                }
                $districtId = null;
                if ($districtName !== null && $districtName !== '') {
                    $districtId = District::query()
                        ->where('city_id', $city->id)
                        ->where('name', $districtName)
                        ->value('id');
                }
                $contract->forceFill([
                    'city_id' => $city->id,
                    'district_id' => $districtId,
                ]);
                $contract->saveQuietly();
            }
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['city', 'district']);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('city')->nullable()->after('developer_number');
            $table->string('district')->nullable()->after('city');
        });

        foreach (Contract::query()->orderBy('id')->cursor() as $contract) {
            $contract->load(['city', 'district']);
            $contract->forceFill([
                'city' => $contract->city?->name,
                'district' => $contract->district?->name,
            ]);
            $contract->saveQuietly();
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['city_id']);
            $table->dropForeign(['district_id']);
            $table->dropColumn(['city_id', 'district_id']);
        });
    }
};
