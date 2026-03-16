# Contract Units CSV Upload – Client Guide

## API endpoint
- **POST** `/api/contracts/units/upload-csv/{contractId}`
- **Body:** form-data with file field **`csv_file`**
- **File:** CSV or TXT, max 10 MB

## Required columns (minimum)
At least **unit_type**, **unit_number**, and **price** are recommended. Other columns are optional.

| Column (English) | Arabic header accepted   | Required | Description |
|------------------|---------------------------|----------|-------------|
| unit_type        | نوع_الوحدة، نوع           | Yes      | e.g. Villa, Apartment, شقق، فيلا |
| unit_number      | رقم_الوحدة، رقم           | Yes      | Unique unit identifier |
| price            | السعر، سعر_الوحدة         | Yes      | Numeric, e.g. 500000 |
| status           | الحالة                    | No       | pending, available, reserved, sold (default: pending) |
| area             | المساحة                   | No       | Numeric (m²) |
| floor            | الطابق                    | No       | Floor number |
| bedrooms         | غرف، عدد_الغرف            | No       | Integer |
| bathrooms        | حمامات، عدد_الحمامات      | No       | Integer |
| private_area_m2  | المساحة_الخاصة، الشرفة    | No       | Balcony/private area (m²). total_area_m2 = area + private_area_m2 (auto) |
| view             | الواجهة، الاتجاه           | No       | e.g. North, East, شمال |
| description_en   | الوصف_انجليزي             | No       | English description |
| description_ar   | الوصف_عربي               | No       | Arabic description |
| description      | الوصف، ملاحظات            | No       | Stored as description_en if description_en is empty |
| diagrames        | diagrams، المخططات        | No       | Unit diagrams (URLs or paths) |

## Sample CSV (English headers)
Use the file **`contract_units_upload_sample.csv`** in the project root for testing.

## Notes
- First row must be the header (column names). Headers are matched case-insensitively.
- Empty rows are skipped.
- **total_area_m2** is computed automatically (area + private_area_m2); do not put it in the CSV.
- Status defaults to **pending** if omitted or invalid.
