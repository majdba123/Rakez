<?php

namespace App\Http\Requests\ExclusiveProject;

use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreExclusiveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('exclusive_projects.request');
    }

    /**
     * Normalize camelCase from frontend to snake_case and resolve developer_number to developer_name.
     * Also supports: top-level "data"/"form" wrapper and "developer" object.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        if (config('app.debug')) {
            Log::debug('Exclusive project request raw', ['keys' => array_keys($input), 'input' => $input]);
        }

        // Unwrap nested payload (e.g. frontend sends { data: { projectName, developer, ... } })
        foreach (['data', 'form', 'payload'] as $key) {
            if (isset($input[$key]) && is_array($input[$key])) {
                $input = array_merge($input, $input[$key]);
                unset($input[$key]);
            }
        }

        // Extract from developer object (e.g. { developer: { name, number } or { value, label } })
        $dev = $input['developer'] ?? null;
        if (is_array($dev) || is_object($dev)) {
            $dev = (array) $dev;
            $name = $dev['name'] ?? $dev['developer_name'] ?? $dev['label'] ?? $dev['value'] ?? null;
            $number = $dev['number'] ?? $dev['developer_number'] ?? $dev['id'] ?? null;
            $contact = $dev['contact'] ?? $dev['developer_contact'] ?? $dev['phone'] ?? $dev['developer_phone'] ?? null;
            if ($name !== null && ($input['developer_name'] ?? '') === '') {
                $input['developer_name'] = is_string($name) ? $name : (string) $name;
            }
            if ($number !== null && ($input['developer_number'] ?? '') === '') {
                $input['developer_number'] = is_string($number) ? $number : (string) $number;
            }
            if ($contact !== null && ($input['developer_contact'] ?? '') === '' && is_scalar($contact)) {
                $input['developer_contact'] = (string) $contact;
            }
        } elseif (is_string($dev) && trim($dev) !== '' && ($input['developer_name'] ?? '') === '') {
            $input['developer_name'] = $dev;
        }

        // Extract from location object (e.g. { location: { city, district } })
        $loc = $input['location'] ?? null;
        if (is_array($loc) || is_object($loc)) {
            $loc = (array) $loc;
            if (isset($loc['city']) && (($input['location_city'] ?? '') === '')) {
                $input['location_city'] = is_scalar($loc['city']) ? $loc['city'] : (string) $loc['city'];
            }
            if (isset($loc['district']) && (($input['location_district'] ?? '') === '')) {
                $input['location_district'] = is_scalar($loc['district']) ? $loc['district'] : (string) $loc['district'];
            }
        }

        // Map camelCase to snake_case so frontend can send either
        $map = [
            'projectName' => 'project_name',
            'developerName' => 'developer_name',
            'developerContact' => 'developer_contact',
            'projectDescription' => 'project_description',
            'estimatedUnits' => 'estimated_units',
            'locationCity' => 'location_city',
            'locationDistrict' => 'location_district',
        ];
        foreach ($map as $camel => $snake) {
            if (array_key_exists($camel, $input) && (!array_key_exists($snake, $input) || $input[$snake] === '' || $input[$snake] === null)) {
                $val = $input[$camel];
                $input[$snake] = is_scalar($val) ? $val : (string) $val;
            }
        }

        // Single-word aliases often sent by frontends
        if (($input['developer_contact'] ?? '') === '' && isset($input['contact']) && is_scalar($input['contact'])) {
            $input['developer_contact'] = (string) $input['contact'];
        }
        if (($input['developer_contact'] ?? '') === '' && isset($input['phone']) && is_scalar($input['phone'])) {
            $input['developer_contact'] = (string) $input['phone'];
        }
        foreach (['developer_phone', 'contact_number', 'contactNumber', 'phone_number', 'phoneNumber'] as $alias) {
            if (($input['developer_contact'] ?? '') === '' && isset($input[$alias]) && is_scalar($input[$alias])) {
                $input['developer_contact'] = (string) $input[$alias];
                break;
            }
        }
        if (($input['location_city'] ?? '') === '' && isset($input['city']) && is_scalar($input['city'])) {
            $input['location_city'] = (string) $input['city'];
        }
        if (($input['project_name'] ?? '') === '' && isset($input['project']) && is_scalar($input['project'])) {
            $input['project_name'] = (string) $input['project'];
        }

        // Resolve developer_number / developerNumber / developer_id to developer_name if developer_name is missing
        $developerName = $input['developer_name'] ?? null;
        $developerNumber = $input['developer_number'] ?? $input['developerNumber'] ?? null;
        $developerId = $input['developer_id'] ?? $input['developerId'] ?? null;
        if (empty($developerName) || trim((string) $developerName) === '') {
            try {
                if (!empty($developerNumber)) {
                    $resolved = Contract::where('developer_number', trim((string) $developerNumber))->value('developer_name');
                    if ($resolved) {
                        $input['developer_name'] = $resolved;
                    }
                }
                if (($input['developer_name'] ?? '') === '' && $developerId !== null && $developerId !== '') {
                    $id = is_numeric($developerId) ? (int) $developerId : trim((string) $developerId);
                    $resolved = Contract::where('id', $id)->value('developer_name')
                        ?? Contract::where('developer_number', $id)->value('developer_name');
                    if ($resolved) {
                        $input['developer_name'] = $resolved;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore (e.g. contracts table missing in tests); validation will fail with "Developer name is required" if needed
            }
        }

        if (config('app.debug')) {
            Log::debug('Exclusive project after normalize', [
                'developer_name' => $input['developer_name'] ?? null,
                'developer_contact' => $input['developer_contact'] ?? null,
                'location_city' => $input['location_city'] ?? null,
                'project_name' => $input['project_name'] ?? null,
            ]);
        }

        $this->merge($input);
    }

    public function rules(): array
    {
        return [
            'project_name' => 'required|string|max:255',
            'developer_name' => 'required|string|max:255',
            'developer_contact' => 'required|string|max:50',
            'project_description' => 'nullable|string|max:2000',
            'estimated_units' => 'nullable|integer|min:1',
            'location_city' => 'required|string|max:100',
            'location_district' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'project_name.required' => 'Project name is required',
            'developer_name.required' => 'Developer name is required',
            'developer_contact.required' => 'Developer contact is required',
            'location_city.required' => 'Location city is required',
            'estimated_units.integer' => 'Estimated units must be a number',
            'estimated_units.min' => 'Estimated units must be at least 1',
        ];
    }

}
