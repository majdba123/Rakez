# Units Calculation Example

## How Totals Are Calculated

When you store a contract with units array, the system automatically calculates three values:

### 1. **units_count** - Total number of units
```
Formula: SUM(unit.count for all units)
```

### 2. **total_units_value** - Total price of all units
```
Formula: SUM(unit.count * unit.price for all units)
```

### 3. **average_unit_price** - Average price per unit
```
Formula: total_units_value / units_count (rounded to 2 decimals)
```

---

## Example 1: Simple Single Unit Type

**Request:**
```json
{
  "project_name": "مشروع الراكز",
  "developer_name": "شركة التطوير",
  "developer_number": "DEV-001",
  "city": "الرياض",
  "district": "الحمراء",
  "developer_requiment": "متطلبات المشروع",
  "units": [
    {
      "type": "شقة",
      "count": 5,
      "price": 500000
    }
  ]
}
```

**Calculation:**
- units_count = 5
- total_units_value = 5 × 500000 = 2,500,000
- average_unit_price = 2,500,000 ÷ 5 = 500,000

**Response:**
```json
{
  "units": [
    {
      "type": "شقة",
      "count": 5,
      "price": 500000
    }
  ],
  "units_count": 5,
  "total_units_value": 2500000,
  "average_unit_price": 500000
}
```

---

## Example 2: Multiple Unit Types

**Request:**
```json
{
  "project_name": "مشروع المحمرية",
  "developer_name": "شركة المشاريع",
  "developer_number": "DEV-002",
  "city": "الرياض",
  "district": "السويدي",
  "developer_requiment": "متطلبات إضافية",
  "units": [
    {
      "type": "شقة",
      "count": 10,
      "price": 500000
    },
    {
      "type": "فيلا",
      "count": 5,
      "price": 1500000
    },
    {
      "type": "محل تجاري",
      "count": 3,
      "price": 250000
    }
  ]
}
```

**Calculation Step by Step:**

| Unit Type | Count | Price | Subtotal (Count × Price) |
|-----------|-------|-------|--------------------------|
| شقة | 10 | 500,000 | 5,000,000 |
| فيلا | 5 | 1,500,000 | 7,500,000 |
| محل تجاري | 3 | 250,000 | 750,000 |
| **TOTAL** | **18** | | **13,250,000** |

**Results:**
- **units_count** = 10 + 5 + 3 = **18**
- **total_units_value** = 5,000,000 + 7,500,000 + 750,000 = **13,250,000**
- **average_unit_price** = 13,250,000 ÷ 18 = **735,277.78**

**Response:**
```json
{
  "units": [
    {
      "type": "شقة",
      "count": 10,
      "price": 500000
    },
    {
      "type": "فيلا",
      "count": 5,
      "price": 1500000
    },
    {
      "type": "محل تجاري",
      "count": 3,
      "price": 250000
    }
  ],
  "units_count": 18,
  "total_units_value": 13250000,
  "average_unit_price": 735277.78
}
```

---

## Example 3: Update Contract with Different Units

**Original Contract (from Example 1):**
- units_count: 5
- total_units_value: 2,500,000
- average_unit_price: 500,000

**Update Request (change units):**
```json
{
  "units": [
    {
      "type": "شقة",
      "count": 8,
      "price": 600000
    },
    {
      "type": "فيلا",
      "count": 2,
      "price": 1200000
    }
  ]
}
```

**New Calculation:**
- units_count = 8 + 2 = **10**
- total_units_value = (8 × 600,000) + (2 × 1,200,000) = 4,800,000 + 2,400,000 = **7,200,000**
- average_unit_price = 7,200,000 ÷ 10 = **720,000**

**Updated Response:**
```json
{
  "units": [
    {
      "type": "شقة",
      "count": 8,
      "price": 600000
    },
    {
      "type": "فيلا",
      "count": 2,
      "price": 1200000
    }
  ],
  "units_count": 10,
  "total_units_value": 7200000,
  "average_unit_price": 720000
}
```

---

## How It Works in Code

### Request Processing
```php
// StoreContractRequest.php
protected function normalizeUnits(): void
{
    $units = $this->input('units', []);
    
    $normalized = [];
    foreach ($units as $unit) {
        // Normalize each unit
        $normalized[] = [
            'type' => trim((string) $unit['type']),
            'count' => (int) $unit['count'],
            'price' => (float) $unit['price'],
        ];
    }
    
    $this->merge(['units' => $normalized]);
}
```

### Model Calculation
```php
// Contract.php
public function calculateUnitTotals(): void
{
    // First normalize all units
    $this->normalizeUnits();
    
    $unitsCount = 0;
    $totalValue = 0;
    
    // Loop through units and calculate totals
    if (is_array($this->units) && count($this->units) > 0) {
        foreach ($this->units as $unit) {
            $count = (int) ($unit['count'] ?? 0);
            $price = (float) ($unit['price'] ?? 0);
            
            $unitsCount += $count;
            $totalValue += ($count * $price);
        }
    }
    
    // Set the calculated totals
    $this->units_count = $unitsCount;
    $this->total_units_value = $totalValue;
    $this->average_unit_price = $unitsCount > 0 
        ? round($totalValue / $unitsCount, 2) 
        : 0;
}
```

### Service Flow
```php
// ContractService.php
public function storeContract(array $data): Contract
{
    // Create the contract
    $contract = Contract::create($data);
    
    // Automatically calculate and save totals
    $contract->calculateUnitTotals();
    $contract->save();
    
    return $contract;
}
```

---

## Field Details

### units
- **Type:** JSON Array
- **Content:** Array of unit objects
- **Each Unit Contains:**
  - `type` (string): Unit type (e.g., "شقة", "فيلا")
  - `count` (integer): Number of units of this type
  - `price` (float): Price per unit

### units_count
- **Type:** Integer
- **Calculated:** Yes (automatically)
- **Editable:** No (read-only)
- **Value:** Sum of all unit counts

### total_units_value
- **Type:** Decimal (2 decimals)
- **Calculated:** Yes (automatically)
- **Editable:** No (read-only)
- **Value:** Sum of (count × price) for all units

### average_unit_price
- **Type:** Decimal (2 decimals)
- **Calculated:** Yes (automatically)
- **Editable:** No (read-only)
- **Value:** total_units_value ÷ units_count

---

## Validation Rules

In `StoreContractRequest.php`:

```php
'units' => 'required|array|min:1',                    // Required, must be array with min 1 item
'units.*.type' => 'required|string|max:255',          // Type required, string, max 255 chars
'units.*.count' => 'required|integer|min:1',          // Count required, integer, minimum 1
'units.*.price' => 'required|numeric|min:0',          // Price required, numeric, minimum 0
```

---

## Error Handling

### Missing Units Array
```json
{
  "success": false,
  "message": "يجب إضافة وحدة واحدة على الأقل"
}
```

### Invalid Unit Count
```json
{
  "success": false,
  "message": "units.0.count: عدد الوحدات يجب أن يكون أكبر من صفر"
}
```

### Invalid Unit Price
```json
{
  "success": false,
  "message": "units.0.price: سعر الوحدة لا يمكن أن يكون سالبًا"
}
```

---

## Summary

✅ **Automatic Calculation** - No manual entry needed for totals
✅ **Normalized Data** - All units properly formatted before calculation
✅ **Type Safe** - Counts as integers, prices as floats
✅ **Rounded Values** - Average price rounded to 2 decimals
✅ **Full Validation** - All units validated before storage
✅ **Easy Updates** - Recalculated automatically on contract update
