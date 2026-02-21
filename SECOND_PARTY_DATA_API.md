# Second Party Data & Contract Units API Documentation
# توثيق API بيانات الطرف الثاني ووحدات العقد

## Authentication - المصادقة

All endpoints require authentication via Bearer Token (Sanctum).
جميع النقاط تتطلب مصادقة عبر Bearer Token.

```
Authorization: Bearer {your_token}
```

## Access Control - التحكم في الوصول

**Required User Type:** `project_management` or `admin`
**نوع المستخدم المطلوب:** `project_management` أو `admin`

---

## Second Party Data Endpoints - نقاط بيانات الطرف الثاني

### 1. Store Second Party Data - إنشاء بيانات الطرف الثاني

Creates second party data for a contract. **Only one record per contract is allowed.**

**Endpoint:** `POST /api/contracts/{contractId}/second-party-data`

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body:**
```json
{
    "real_estate_papers_url": "https://example.com/papers.pdf",
    "plans_equipment_docs_url": "https://example.com/plans.pdf",
    "project_logo_url": "https://example.com/logo.png",
    "prices_units_url": "https://example.com/prices.pdf",
    "marketing_license_url": "https://example.com/license.pdf",
    "advertiser_section_url": "https://example.com/advertiser.pdf"
}
```

**Success Response (201):**
```json
{
    "success": true,
    "message": "تم حفظ بيانات الطرف الثاني بنجاح",
    "data": {
        "id": 1,
        "contract_id": 5,
        "real_estate_papers_url": "https://example.com/papers.pdf",
        "plans_equipment_docs_url": "https://example.com/plans.pdf",
        "project_logo_url": "https://example.com/logo.png",
        "prices_units_url": "https://example.com/prices.pdf",
        "marketing_license_url": "https://example.com/license.pdf",
        "advertiser_section_url": "https://example.com/advertiser.pdf",
        "contract_units": [],
        "processed_by": {
            "id": 3,
            "name": "أحمد محمد",
            "type": "project_management"
        },
        "processed_at": "2025-12-31T12:30:00+00:00",
        "created_at": "2025-12-31T12:30:00+00:00",
        "updated_at": "2025-12-31T12:30:00+00:00"
    }
}
```

**Error Response - Already Exists (422):**
```json
{
    "success": false,
    "message": "بيانات الطرف الثاني موجودة بالفعل لهذا العقد"
}
```

**Error Response - No Contract Info (422):**
```json
{
    "success": false,
    "message": "يجب أن يكون العقد لديه معلومات عليه قبل إضافة بيانات الطرف الثاني"
}
```

**Error Response - Unauthorized (403):**
```json
{
    "success": false,
    "message": "غير مصرح - هذه الصلاحية متاحة فقط لإدارة المشاريع"
}
```

---

### 2. Get Second Party Data - عرض بيانات الطرف الثاني

**Endpoint:** `GET /api/contracts/{contractId}/second-party-data`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "contract_id": 5,
        "real_estate_papers_url": "https://example.com/papers.pdf",
        "plans_equipment_docs_url": "https://example.com/plans.pdf",
        "project_logo_url": "https://example.com/logo.png",
        "prices_units_url": "https://example.com/prices.pdf",
        "marketing_license_url": "https://example.com/license.pdf",
        "advertiser_section_url": "https://example.com/advertiser.pdf",
        "contract_units": [
            {
                "id": 1,
                "second_party_data_id": 1,
                "contract_id": 5,
                "unit_type": "شقة",
                "unit_number": "A101",
                "count": 1,
                "status": "pending",
                "price": 500000.00,
                "total_price": 500000.00,
                "area": 150.00,
                "description": "شقة غرفتين",
                "created_at": "2025-12-31T12:35:00+00:00",
                "updated_at": "2025-12-31T12:35:00+00:00"
            }
        ],
        "processed_by": {
            "id": 3,
            "name": "أحمد محمد",
            "type": "project_management"
        },
        "processed_at": "2025-12-31T12:30:00+00:00",
        "created_at": "2025-12-31T12:30:00+00:00",
        "updated_at": "2025-12-31T12:30:00+00:00"
    }
}
```

**Error Response - Not Found (404):**
```json
{
    "success": false,
    "message": "بيانات الطرف الثاني غير موجودة لهذا العقد"
}
```

---

### 3. Update Second Party Data - تحديث بيانات الطرف الثاني

**Endpoint:** `PUT /api/contracts/{contractId}/second-party-data`

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body (partial update allowed):**
```json
{
    "real_estate_papers_url": "https://example.com/new-papers.pdf",
    "marketing_license_url": "https://example.com/new-license.pdf"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "تم تحديث بيانات الطرف الثاني بنجاح",
    "data": {
        "id": 1,
        "contract_id": 5,
        "real_estate_papers_url": "https://example.com/new-papers.pdf",
        "plans_equipment_docs_url": "https://example.com/plans.pdf",
        "project_logo_url": "https://example.com/logo.png",
        "prices_units_url": "https://example.com/prices.pdf",
        "marketing_license_url": "https://example.com/new-license.pdf",
        "advertiser_section_url": "https://example.com/advertiser.pdf",
        "contract_units": [],
        "processed_by": {
            "id": 3,
            "name": "أحمد محمد",
            "type": "project_management"
        },
        "processed_at": "2025-12-31T14:00:00+00:00",
        "created_at": "2025-12-31T12:30:00+00:00",
        "updated_at": "2025-12-31T14:00:00+00:00"
    }
}
```

---

## Contract Units Endpoints - نقاط وحدات العقد

### 4. Upload CSV - رفع ملف CSV

Uploads a CSV file to create contract units. **Only one CSV upload is allowed per SecondPartyData.**

**Endpoint:** `POST /api/second-party-data/{secondPartyDataId}/units/upload-csv`

**Headers:**
```
Content-Type: multipart/form-data
Authorization: Bearer {token}
```

**Request Body (form-data):**
```
csv_file: [file.csv]
```

**CSV File Format:**
```csv
unit_type,unit_number,count,price,total_price,area,description
شقة,A101,1,500000,500000,150,شقة غرفتين
فيلا,V201,1,1200000,1200000,350,فيلا مستقلة
دوبلكس,D301,1,800000,800000,250,دوبلكس طابقين
```

**Supported Column Names (Arabic & English):**
| Field | Accepted Names |
|-------|---------------|
| unit_type | unit_type, type, نوع_الوحدة, نوع |
| unit_number | unit_number, number, رقم_الوحدة, رقم |
| count | count, quantity, العدد, الكمية |
| status | status, الحالة |
| price | price, unit_price, السعر, سعر_الوحدة |
| total_price | total_price, total, السعر_الإجمالي, الإجمالي |
| area | area, size, المساحة |
| description | description, desc, الوصف, ملاحظات |

**Success Response (202 Accepted):**
```json
{
    "success": true,
    "message": "تم استلام الملف وسيتم معالجته في الخلفية",
    "data": {
        "status": "processing",
        "second_party_data_id": 1
    }
}
```

**Error Response - Already Uploaded (422):**
```json
{
    "success": false,
    "message": "تم رفع ملف CSV مسبقاً لهذا العقد. لا يمكن رفع ملف آخر"
}
```

**Error Response - Invalid File (422):**
```json
{
    "success": false,
    "message": "ملف CSV مطلوب"
}
```

---

### 5. List Units by SecondPartyData - عرض الوحدات

**Endpoint:** `GET /api/second-party-data/{secondPartyDataId}/units`

**Query Parameters:**
- `per_page` (optional, default: 15) - عدد النتائج في الصفحة

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "second_party_data_id": 1,
            "contract_id": 5,
            "unit_type": "شقة",
            "unit_number": "A101",
            "count": 1,
            "status": "pending",
            "price": 500000.00,
            "total_price": 500000.00,
            "area": 150.00,
            "description": "شقة غرفتين",
            "created_at": "2025-12-31T12:35:00+00:00",
            "updated_at": "2025-12-31T12:35:00+00:00"
        },
        {
            "id": 2,
            "second_party_data_id": 1,
            "contract_id": 5,
            "unit_type": "فيلا",
            "unit_number": "V201",
            "count": 1,
            "status": "pending",
            "price": 1200000.00,
            "total_price": 1200000.00,
            "area": 350.00,
            "description": "فيلا مستقلة",
            "created_at": "2025-12-31T12:35:00+00:00",
            "updated_at": "2025-12-31T12:35:00+00:00"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 15,
        "total": 2
    }
}
```

---

### 6. List Units by Contract ID - عرض الوحدات حسب العقد

**Endpoint:** `GET /api/contracts/{contractId}/units`

**Query Parameters:**
- `per_page` (optional, default: 15)

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):** Same as above

---

### 7. Get Single Unit - عرض وحدة واحدة

**Endpoint:** `GET /api/units/{unitId}`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "second_party_data_id": 1,
        "contract_id": 5,
        "unit_type": "شقة",
        "unit_number": "A101",
        "count": 1,
        "status": "pending",
        "price": 500000.00,
        "total_price": 500000.00,
        "area": 150.00,
        "description": "شقة غرفتين",
        "created_at": "2025-12-31T12:35:00+00:00",
        "updated_at": "2025-12-31T12:35:00+00:00"
    }
}
```

**Error Response (404):**
```json
{
    "success": false,
    "message": "الوحدة غير موجودة"
}
```

---

### 8. Update Unit - تحديث وحدة

**Endpoint:** `PUT /api/units/{unitId}`

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body (partial update allowed):**
```json
{
    "unit_type": "شقة فاخرة",
    "status": "sold",
    "price": 550000,
    "total_price": 550000,
    "description": "شقة غرفتين - تم البيع"
}
```

**Available Status Values:**
- `pending` - معلق
- `sold` - مباع
- `reserved` - محجوز
- `available` - متاح

**Success Response (200):**
```json
{
    "success": true,
    "message": "تم تحديث الوحدة بنجاح",
    "data": {
        "id": 1,
        "second_party_data_id": 1,
        "contract_id": 5,
        "unit_type": "شقة فاخرة",
        "unit_number": "A101",
        "count": 1,
        "status": "sold",
        "price": 550000.00,
        "total_price": 550000.00,
        "area": 150.00,
        "description": "شقة غرفتين - تم البيع",
        "created_at": "2025-12-31T12:35:00+00:00",
        "updated_at": "2025-12-31T15:00:00+00:00"
    }
}
```

---

### 9. Get Units Statistics - إحصائيات الوحدات

**Endpoint:** `GET /api/second-party-data/{secondPartyDataId}/units/stats`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
    "success": true,
    "data": {
        "total_count": 10,
        "total_units": 15,
        "total_value": 8500000.00,
        "total_area": 2500.00,
        "by_status": {
            "pending": 5,
            "sold": 3,
            "reserved": 2
        }
    }
}
```

---

## Postman Collection Import

You can import this collection into Postman:

```json
{
    "info": {
        "name": "Rakez ERP - Second Party Data & Units API",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "variable": [
        {
            "key": "base_url",
            "value": "http://localhost:8000/api"
        },
        {
            "key": "token",
            "value": "your_bearer_token_here"
        }
    ],
    "auth": {
        "type": "bearer",
        "bearer": [
            {
                "key": "token",
                "value": "{{token}}",
                "type": "string"
            }
        ]
    },
    "item": [
        {
            "name": "Second Party Data",
            "item": [
                {
                    "name": "Store Second Party Data",
                    "request": {
                        "method": "POST",
                        "header": [
                            {
                                "key": "Content-Type",
                                "value": "application/json"
                            }
                        ],
                        "body": {
                            "mode": "raw",
                            "raw": "{\n    \"real_estate_papers_url\": \"https://example.com/papers.pdf\",\n    \"plans_equipment_docs_url\": \"https://example.com/plans.pdf\",\n    \"project_logo_url\": \"https://example.com/logo.png\",\n    \"prices_units_url\": \"https://example.com/prices.pdf\",\n    \"marketing_license_url\": \"https://example.com/license.pdf\",\n    \"advertiser_section_url\": \"https://example.com/advertiser.pdf\"\n}"
                        },
                        "url": {
                            "raw": "{{base_url}}/contracts/1/second-party-data",
                            "host": ["{{base_url}}"],
                            "path": ["contracts", "1", "second-party-data"]
                        }
                    }
                },
                {
                    "name": "Get Second Party Data",
                    "request": {
                        "method": "GET",
                        "url": {
                            "raw": "{{base_url}}/contracts/1/second-party-data",
                            "host": ["{{base_url}}"],
                            "path": ["contracts", "1", "second-party-data"]
                        }
                    }
                },
                {
                    "name": "Update Second Party Data",
                    "request": {
                        "method": "PUT",
                        "header": [
                            {
                                "key": "Content-Type",
                                "value": "application/json"
                            }
                        ],
                        "body": {
                            "mode": "raw",
                            "raw": "{\n    \"real_estate_papers_url\": \"https://example.com/updated-papers.pdf\"\n}"
                        },
                        "url": {
                            "raw": "{{base_url}}/contracts/1/second-party-data",
                            "host": ["{{base_url}}"],
                            "path": ["contracts", "1", "second-party-data"]
                        }
                    }
                }
            ]
        },
        {
            "name": "Contract Units",
            "item": [
                {
                    "name": "Upload CSV",
                    "request": {
                        "method": "POST",
                        "body": {
                            "mode": "formdata",
                            "formdata": [
                                {
                                    "key": "csv_file",
                                    "type": "file",
                                    "src": ""
                                }
                            ]
                        },
                        "url": {
                            "raw": "{{base_url}}/second-party-data/1/units/upload-csv",
                            "host": ["{{base_url}}"],
                            "path": ["second-party-data", "1", "units", "upload-csv"]
                        }
                    }
                },
                {
                    "name": "List Units by SecondPartyData",
                    "request": {
                        "method": "GET",
                        "url": {
                            "raw": "{{base_url}}/second-party-data/1/units?per_page=15",
                            "host": ["{{base_url}}"],
                            "path": ["second-party-data", "1", "units"],
                            "query": [
                                {
                                    "key": "per_page",
                                    "value": "15"
                                }
                            ]
                        }
                    }
                },
                {
                    "name": "List Units by Contract",
                    "request": {
                        "method": "GET",
                        "url": {
                            "raw": "{{base_url}}/contracts/1/units?per_page=15",
                            "host": ["{{base_url}}"],
                            "path": ["contracts", "1", "units"],
                            "query": [
                                {
                                    "key": "per_page",
                                    "value": "15"
                                }
                            ]
                        }
                    }
                },
                {
                    "name": "Get Single Unit",
                    "request": {
                        "method": "GET",
                        "url": {
                            "raw": "{{base_url}}/units/1",
                            "host": ["{{base_url}}"],
                            "path": ["units", "1"]
                        }
                    }
                },
                {
                    "name": "Update Unit",
                    "request": {
                        "method": "PUT",
                        "header": [
                            {
                                "key": "Content-Type",
                                "value": "application/json"
                            }
                        ],
                        "body": {
                            "mode": "raw",
                            "raw": "{\n    \"status\": \"sold\",\n    \"price\": 550000,\n    \"description\": \"تم البيع\"\n}"
                        },
                        "url": {
                            "raw": "{{base_url}}/units/1",
                            "host": ["{{base_url}}"],
                            "path": ["units", "1"]
                        }
                    }
                },
                {
                    "name": "Get Units Stats",
                    "request": {
                        "method": "GET",
                        "url": {
                            "raw": "{{base_url}}/second-party-data/1/units/stats",
                            "host": ["{{base_url}}"],
                            "path": ["second-party-data", "1", "units", "stats"]
                        }
                    }
                }
            ]
        }
    ]
}
```

---

## Error Codes Summary - ملخص رموز الأخطاء

| Code | Description | الوصف |
|------|-------------|-------|
| 200 | Success | نجاح |
| 201 | Created | تم الإنشاء |
| 202 | Accepted (Processing) | مقبول (قيد المعالجة) |
| 401 | Unauthorized | غير مصرح - تسجيل الدخول مطلوب |
| 403 | Forbidden | ممنوع - صلاحيات غير كافية |
| 404 | Not Found | غير موجود |
| 422 | Validation Error | خطأ في التحقق |
| 500 | Server Error | خطأ في الخادم |

