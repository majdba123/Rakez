# API Examples: Sales Module

## Dashboard

### Get Dashboard KPIs
`GET /api/sales/dashboard`

**Request Query Params:**
- `scope`: `me` (default) or `team`
- `from`: `YYYY-MM-DD`
- `to`: `YYYY-MM-DD`

**Response:**
```json
{
    "success": true,
    "data": {
        "total_sales": 150000,
        "reservations_count": 12,
        "target_progress": 75
    }
}
```

## Reservations

### Create Reservation
`POST /api/sales/reservations`

**Request Body:**
```json
{
    "contract_id": 1,
    "contract_unit_id": 5,
    "reservation_type": "negotiation",
    "contract_date": "2026-01-26",
    "client_name": "Ahmed Ali",
    "client_mobile": "0551234567",
    "client_nationality": "Saudi",
    "client_iban": "SA123...",
    "payment_method": "bank_transfer",
    "down_payment_amount": 10000,
    "down_payment_status": "paid",
    "purchase_mechanism": "cash"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Reservation created successfully",
    "data": {
        "id": 101,
        "status": "under_negotiation",
        "voucher_pdf_url": "http://.../vouchers/v_101.pdf"
    }
}
```

## Targets

### Update Target Progress
`PATCH /api/sales/targets/{id}`

**Request Body:**
```json
{
    "current_value": 50000,
    "notes": "Closing another deal next week"
}
```
