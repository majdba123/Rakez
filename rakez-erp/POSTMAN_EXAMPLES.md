# Postman API Examples

## 1. Store Contract with Units Array

**Method:** `POST`

**URL:** `http://localhost/api/contracts/store`

**Headers:**
```
Authorization: Bearer YOUR_AUTH_TOKEN
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
  "project_name": "مشروع برج الراكز",
  "developer_name": "شركة التطوير السكني",
  "developer_number": "DEV-2025-001",
  "city": "الرياض",
  "district": "الحمراء",
  "developer_requiment": "متطلبات المشروع الخاصة",
  "project_image_url": "https://example.com/image.jpg",
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

**Response (Success - 201):**
```json
{
  "data": {
    "id": 1,
    "user_id": 5,
    "project_name": "مشروع برج الراكز",
    "developer_name": "شركة التطوير السكني",
    "developer_number": "DEV-2025-001",
    "city": "الرياض",
    "district": "الحمراء",
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
    ],
    "units_count": 10,
    "total_units_value": 6250000,
    "average_unit_price": 625000,
    "status": "pending",
    "notes": null,
    "developer_requiment": "متطلبات المشروع الخاصة",
    "project_image_url": "https://example.com/image.jpg",
    "created_at": "2025-12-23T10:30:00.000000Z",
    "updated_at": "2025-12-23T10:30:00.000000Z",
    "user": {
      "id": 5,
      "name": "أحمد محمد",
      "email": "ahmed@example.com",
      "phone": "0501234567",
      "type": "developer"
    },
    "info": null
  }
}
```

---

## 2. Update Contract with New Units

**Method:** `PUT`

**URL:** `http://localhost/api/contracts/{id}/update`

**Headers:**
```
Authorization: Bearer YOUR_AUTH_TOKEN
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
  "project_name": "مشروع برج الراكز - النسخة المحدثة",
  "units": [
    {
      "type": "شقة",
      "count": 5,
      "price": 550000
    },
    {
      "type": "فيلا",
      "count": 3,
      "price": 1800000
    }
  ]
}
```

**Response (Success - 200):**
```json
{
  "data": {
    "id": 1,
    "user_id": 5,
    "project_name": "مشروع برج الراكز - النسخة المحدثة",
    "developer_name": "شركة التطوير السكني",
    "developer_number": "DEV-2025-001",
    "city": "الرياض",
    "district": "الحمراء",
    "units": [
      {
        "type": "شقة",
        "count": 5,
        "price": 550000
      },
      {
        "type": "فيلا",
        "count": 3,
        "price": 1800000
      }
    ],
    "units_count": 8,
    "total_units_value": 7150000,
    "average_unit_price": 893750,
    "status": "pending",
    "notes": null,
    "developer_requiment": "متطلبات المشروع الخاصة",
    "project_image_url": "https://example.com/image.jpg",
    "created_at": "2025-12-23T10:30:00.000000Z",
    "updated_at": "2025-12-23T11:45:00.000000Z",
    "user": {
      "id": 5,
      "name": "أحمد محمد",
      "email": "ahmed@example.com",
      "phone": "0501234567",
      "type": "developer"
    },
    "info": null
  }
}
```

---

## 3. Get Contract by ID

**Method:** `GET`

**URL:** `http://localhost/api/contracts/{id}`

**Headers:**
```
Authorization: Bearer YOUR_AUTH_TOKEN
Accept: application/json
```

**Response (Success - 200):**
```json
{
  "data": {
    "id": 1,
    "user_id": 5,
    "project_name": "مشروع برج الراكز",
    "developer_name": "شركة التطوير السكني",
    "developer_number": "DEV-2025-001",
    "city": "الرياض",
    "district": "الحمراء",
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
      }
    ],
    "units_count": 5,
    "total_units_value": 4500000,
    "average_unit_price": 900000,
    "status": "pending",
    "notes": null,
    "developer_requiment": "متطلبات المشروع الخاصة",
    "project_image_url": "https://example.com/image.jpg",
    "created_at": "2025-12-23T10:30:00.000000Z",
    "updated_at": "2025-12-23T10:30:00.000000Z",
    "user": {
      "id": 5,
      "name": "أحمد محمد",
      "email": "ahmed@example.com",
      "phone": "0501234567",
      "type": "developer"
    },
    "info": {
      "id": 1,
      "contract_id": 1,
      "contract_number": "ER-1-1703330400",
      "first_party_name": "شركة راكز العقارية",
      "first_party_cr_number": "1010650301",
      "first_party_signatory": "عبد العزيز خالد عبد العزيز الجلعود",
      "first_party_phone": "0935027218",
      "first_party_email": "info@rakez.sa",
      "second_party_name": "أحمد محمد",
      "second_party_id": "1234567890",
      "second_party_phone": "0501234567",
      "second_party_email": "ahmed@example.com",
      "created_at": "2025-12-23T10:35:00.000000Z",
      "updated_at": "2025-12-23T10:35:00.000000Z"
    }
  }
}
```

---

## 4. Get All Contracts (Index)

**Method:** `GET`

**URL:** `http://localhost/api/contracts?status=pending&city=الرياض&page=1`

**Headers:**
```
Authorization: Bearer YOUR_AUTH_TOKEN
Accept: application/json
```

**Query Parameters:**
- `status`: `pending`, `approved`, `rejected`, `completed` (optional)
- `city`: City name (optional)
- `district`: District name (optional)
- `project_name`: Search by project name (optional)
- `user_id`: Filter by user (optional)
- `page`: Page number (default: 1)

**Response (Success - 200):**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "project_name": "مشروع برج الراكز",
      "developer_name": "شركة التطوير السكني",
      "developer_number": "DEV-2025-001",
      "city": "الرياض",
      "district": "الحمراء",
      "units_count": 5,
      "total_units_value": 4500000,
      "average_unit_price": 900000,
      "status": "pending",
      "created_at": "2025-12-23T10:30:00.000000Z",
      "updated_at": "2025-12-23T10:30:00.000000Z",
      "user": {
        "id": 5,
        "name": "أحمد محمد",
        "email": "ahmed@example.com",
        "phone": "0501234567",
        "type": "developer"
      }
    },
    {
      "id": 2,
      "user_id": 6,
      "project_name": "مشروع الواحة",
      "developer_name": "شركة الإنشاءات المتقدمة",
      "developer_number": "DEV-2025-002",
      "city": "الرياض",
      "district": "السويدي",
      "units_count": 8,
      "total_units_value": 6400000,
      "average_unit_price": 800000,
      "status": "approved",
      "created_at": "2025-12-22T14:20:00.000000Z",
      "updated_at": "2025-12-22T14:20:00.000000Z",
      "user": {
        "id": 6,
        "name": "فاطمة علي",
        "email": "fatima@example.com",
        "phone": "0559876543",
        "type": "developer"
      }
    }
  ],
  "links": {
    "first": "http://localhost/api/contracts?page=1",
    "last": "http://localhost/api/contracts?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "http://localhost/api/contracts",
    "per_page": 15,
    "to": 2,
    "total": 2
  }
}
```

---

## 5. Store Contract Info

**Method:** `POST`

**URL:** `http://localhost/api/contracts/{id}/store-info`

**Headers:**
```
Authorization: Bearer YOUR_AUTH_TOKEN
Content-Type: application/json
Accept: application/json
```

**Requirements:**
- Contract must have status `approved`
- User must be contract owner or admin

**Body (JSON):**
```json
{
  "second_party_name": "أحمد محمد علي",
  "second_party_id": "1234567890",
  "second_party_phone": "0501234567",
  "second_party_email": "ahmed@example.com"
}
```

**Response (Success - 201):**
```json
{
  "data": {
    "id": 1,
    "contract_id": 1,
    "contract_number": "ER-1-1703330400",
    "first_party_name": "شركة راكز العقارية",
    "first_party_cr_number": "1010650301",
    "first_party_signatory": "عبد العزيز خالد عبد العزيز الجلعود",
    "first_party_phone": "0935027218",
    "first_party_email": "info@rakez.sa",
    "second_party_name": "أحمد محمد علي",
    "second_party_id": "1234567890",
    "second_party_phone": "0501234567",
    "second_party_email": "ahmed@example.com",
    "created_at": "2025-12-23T10:35:00.000000Z",
    "updated_at": "2025-12-23T10:35:00.000000Z"
  }
}
```

---

## 6. Update Contract Status

**Method:** `PUT`

**URL:** `http://localhost/api/contracts/{id}/update-status`

**Headers:**
```
Authorization: Bearer YOUR_AUTH_TOKEN
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
  "status": "approved"
}
```

**Valid Status Values:**
- `pending` - Initial status
- `approved` - Approved by admin
- `rejected` - Rejected by admin
- `completed` - Completed

**Response (Success - 200):**
```json
{
  "data": {
    "id": 1,
    "user_id": 5,
    "project_name": "مشروع برج الراكز",
    "developer_name": "شركة التطوير السكني",
    "developer_number": "DEV-2025-001",
    "city": "الرياض",
    "district": "الحمراء",
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
      }
    ],
    "units_count": 5,
    "total_units_value": 4500000,
    "average_unit_price": 900000,
    "status": "approved",
    "notes": null,
    "developer_requiment": "متطلبات المشروع الخاصة",
    "project_image_url": "https://example.com/image.jpg",
    "created_at": "2025-12-23T10:30:00.000000Z",
    "updated_at": "2025-12-23T11:00:00.000000Z",
    "user": {
      "id": 5,
      "name": "أحمد محمد",
      "email": "ahmed@example.com",
      "phone": "0501234567",
      "type": "developer"
    },
    "info": null
  }
}
```

---

## Units Array Structure

### Single Unit Object:
```json
{
  "type": "شقة",
  "count": 3,
  "price": 500000
}
```

### Fields Explanation:
- **type** (string, required): Unit type (e.g., "شقة", "فيلا", "محل تجاري")
- **count** (integer, required): Number of units of this type (minimum: 1)
- **price** (decimal, required): Price per unit (minimum: 0)

### Automatic Calculations:
When you store/update a contract, the system automatically calculates:
- **units_count**: Sum of all unit counts
  - Formula: `sum(units[].count)`
  - Example: 3 + 2 + 5 = 10

- **total_units_value**: Total value of all units
  - Formula: `sum(units[].count * units[].price)`
  - Example: (3 × 500000) + (2 × 1500000) + (5 × 250000) = 6,250,000

- **average_unit_price**: Average price per unit
  - Formula: `total_units_value / units_count`
  - Example: 6,250,000 / 10 = 625,000

---

## Error Responses

### 422 - Validation Error:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "units": ["The units field is required."],
    "units.0.count": ["The units.0.count field must be at least 1."],
    "units.1.price": ["The units.1.price must be a number."]
  }
}
```

### 401 - Unauthorized:
```json
{
  "message": "Unauthorized to perform this action."
}
```

### 403 - Forbidden (Contract not approved):
```json
{
  "message": "Contract must be approved before storing info."
}
```

### 404 - Not Found:
```json
{
  "message": "Contract not found."
}
```

---

## Testing Steps

1. **Create a Contract** with units array
2. **Verify Response** shows calculated totals
3. **Update Contract** with different units
4. **Check Recalculation** of totals
5. **Approve Contract** (admin only)
6. **Store Contract Info** after approval
7. **Get Contract** to verify all data is saved correctly
