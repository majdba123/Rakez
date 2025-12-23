# Contract Units Calculation - Direct in Model

## How It Works

When you create or update a contract with units array, the model automatically calculates the totals:

### Example 1: Simple Calculation

**Input Request:**
```json
{
  "project_name": "مشروع براكز",
  "developer_name": "شركة التطوير",
  "developer_number": "DEV001",
  "city": "الرياض",
  "district": "الحمراء",
  "units": [
    {
      "type": "شقة",
      "count": 3,
      "price": 500000
    }
  ]
}
```

**Calculation Flow:**
```
1. Model receives data
2. normalizeUnits() is called:
   - Trims "شقة" to "شقة"
   - Casts count to integer: 3
   - Casts price to float: 500000.0

3. calculateUnitTotals() is called:
   - units_count = sum of all counts = 3
   - total_units_value = (3 × 500000) = 1,500,000
   - average_unit_price = 1,500,000 ÷ 3 = 500,000

4. Database stores:
   ✅ units: [{"type": "شقة", "count": 3, "price": 500000}]
   ✅ units_count: 3
   ✅ total_units_value: 1500000.00
   ✅ average_unit_price: 500000.00
```

---

## Example 2: Multiple Unit Types

**Input Request:**
```json
{
  "units": [
    {
      "type": "شقة",
      "count": 3,
      "price": 500000
    },
    {
      "type": "فيلا",
      "count": 2,
      "price": 1500000
    },
    {
      "type": "محل تجاري",
      "count": 5,
      "price": 250000
    }
  ]
}
```

**Calculation Process:**

```
Iteration 1 (شقة):
  count = 3
  price = 500000
  value = 3 × 500000 = 1,500,000
  unitsCount = 0 + 3 = 3
  totalValue = 0 + 1,500,000 = 1,500,000

Iteration 2 (فيلا):
  count = 2
  price = 1500000
  value = 2 × 1500000 = 3,000,000
  unitsCount = 3 + 2 = 5
  totalValue = 1,500,000 + 3,000,000 = 4,500,000

Iteration 3 (محل تجاري):
  count = 5
  price = 250000
  value = 5 × 250000 = 1,250,000
  unitsCount = 5 + 5 = 10
  totalValue = 4,500,000 + 1,250,000 = 5,750,000

Final Totals:
  ✅ units_count = 10
  ✅ total_units_value = 5,750,000
  ✅ average_unit_price = 5,750,000 ÷ 10 = 575,000
```

**Database Result:**
```json
{
  "units": [
    {"type": "شقة", "count": 3, "price": 500000},
    {"type": "فيلا", "count": 2, "price": 1500000},
    {"type": "محل تجاري", "count": 5, "price": 250000}
  ],
  "units_count": 10,
  "total_units_value": 5750000,
  "average_unit_price": 575000
}
```

---

## Implementation in Model

```php
public function calculateUnitTotals(): void
{
    // Normalize units first
    $this->normalizeUnits();

    // Initialize totals
    $unitsCount = 0;
    $totalValue = 0;

    // Calculate from units array
    if (is_array($this->units) && count($this->units) > 0) {
        foreach ($this->units as $unit) {
            // Get count and price from unit
            $count = (int) ($unit['count'] ?? 0);
            $price = (float) ($unit['price'] ?? 0);

            // Add to running totals
            $unitsCount += $count;
            $totalValue += ($count * $price);
        }
    }

    // Set calculated values
    $this->units_count = $unitsCount;
    $this->total_units_value = $totalValue;
    // Prevent division by zero
    $this->average_unit_price = $unitsCount > 0 ? ($totalValue / $unitsCount) : 0;
}
```

---

## Where It's Called

### During Store (Create)
```php
public function storeContract(array $data): Contract
{
    // ... validation ...
    
    $contract = Contract::create($data);
    
    // Calculate units totals automatically
    $contract->calculateUnitTotals();  // ← Called here
    $contract->save();
    
    return $contract;
}
```

### During Update
```php
public function updateContract(int $id, array $data, int $userId = null): Contract
{
    $contract = Contract::findOrFail($id);
    
    // Update data
    $contract->update($data);
    
    // Recalculate if units changed
    if (isset($data['units']) && is_array($data['units'])) {
        $contract->calculateUnitTotals();  // ← Called here
        $contract->save();
    }
    
    return $contract->fresh(['user', 'info']);
}
```

---

## Formula Breakdown

### units_count
```
units_count = sum of all unit counts

Formula: Σ(unit.count)
Example: 3 + 2 + 5 = 10
```

### total_units_value
```
total_units_value = sum of (count × price) for each unit

Formula: Σ(unit.count × unit.price)
Example: (3 × 500k) + (2 × 1500k) + (5 × 250k)
       = 1500k + 3000k + 1250k
       = 5,750,000
```

### average_unit_price
```
average_unit_price = total_units_value ÷ units_count

Formula: total_units_value / units_count
Example: 5,750,000 / 10 = 575,000
```

---

## Database Schema

The database stores these as:
- `units` (JSON) - The array of units
- `units_count` (INTEGER) - Total count of all units
- `total_units_value` (DECIMAL 2) - Total value (rounded to 2 decimals)
- `average_unit_price` (DECIMAL 2) - Average price (rounded to 2 decimals)

---

## API Response Example

When you retrieve a contract, you get:

```json
{
  "id": 1,
  "project_name": "مشروع براكز",
  "units": [
    {"type": "شقة", "count": 3, "price": 500000},
    {"type": "فيلا", "count": 2, "price": 1500000},
    {"type": "محل تجاري", "count": 5, "price": 250000}
  ],
  "units_count": 10,
  "total_units_value": 5750000,
  "average_unit_price": 575000
}
```

---

## Edge Cases Handled

### 1. Empty Units Array
```php
$contract->units = [];
$contract->calculateUnitTotals();

Result:
- units_count = 0
- total_units_value = 0
- average_unit_price = 0 (prevents division by zero)
```

### 2. Invalid Unit Data
```php
$contract->units = [
  {"type": "شقة"},  // Missing count and price
  {"type": "فيلا", "count": 2, "price": 1500000}
];
$contract->calculateUnitTotals();

After normalizeUnits():
- Invalid unit is removed
- Only valid unit remains
- Calculation proceeds with valid units only

Result:
- units_count = 2
- total_units_value = 3000000
- average_unit_price = 1500000
```

### 3. Zero Count
```php
$contract->units = [
  {"type": "شقة", "count": 0, "price": 500000},
  {"type": "فيلا", "count": 2, "price": 1500000}
];

Result:
- units_count = 0 + 2 = 2
- total_units_value = (0 × 500k) + (2 × 1500k) = 3,000,000
- average_unit_price = 3,000,000 ÷ 2 = 1,500,000
```

---

## Testing the Calculation

### Test Case 1: Create with Units
```bash
curl -X POST http://localhost/api/contracts/store \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "project_name": "Test",
    "developer_name": "Test Dev",
    "developer_number": "DEV001",
    "city": "الرياض",
    "district": "الحمراء",
    "developer_requiment": "Test",
    "units": [
      {"type": "شقة", "count": 3, "price": 500000},
      {"type": "فيلا", "count": 2, "price": 1500000}
    ]
  }'
```

**Expected Response (201):**
```json
{
  "units": [...],
  "units_count": 5,
  "total_units_value": 4500000,
  "average_unit_price": 900000
}
```

### Test Case 2: Update Units
```bash
curl -X PUT http://localhost/api/contracts/1/update \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "units": [
      {"type": "شقة", "count": 5, "price": 600000}
    ]
  }'
```

**Expected Response (200):**
```json
{
  "units": [...],
  "units_count": 5,
  "total_units_value": 3000000,
  "average_unit_price": 600000
}
```

---

## Summary

✅ **Direct Calculation in Model** - No external service needed
✅ **Automatic Normalization** - Data cleaned before calculation
✅ **Proper Type Casting** - Integers and floats handled correctly
✅ **Edge Cases Covered** - Empty arrays and invalid data handled
✅ **Division by Zero Protected** - Uses conditional check
✅ **Clean and Simple** - Easy to understand and maintain
✅ **Always Accurate** - Recalculated on every save

Generated: December 23, 2025
