# Migration filename renames (collision fix)

The following files were renamed so Laravel runs them in a deterministic order on **fresh** installs:

| Old filename | New filename |
|--------------|--------------|
| `2026_01_22_000001_add_status_to_photography_departments_table.php` | `2026_01_22_000003_add_status_to_photography_departments_table.php` |
| `2026_03_11_000001_backfill_total_area_m2_contract_units.php` | `2026_03_11_000005_backfill_total_area_m2_contract_units.php` |
| `2026_03_11_000002_replace_lat_lng_with_location_url_contract_infos.php` | `2026_03_11_000006_replace_lat_lng_with_location_url_contract_infos.php` |
| `2026_03_17_000001_create_ai_interaction_logs_table.php` | `2026_03_17_000004_create_ai_interaction_logs_table.php` |

## Existing databases

If these migrations **already ran** under the old names, update the `migrations` table so Laravel does not try to run them again:

```sql
UPDATE migrations SET migration = '2026_01_22_000003_add_status_to_photography_departments_table' WHERE migration = '2026_01_22_000001_add_status_to_photography_departments_table';
UPDATE migrations SET migration = '2026_03_11_000005_backfill_total_area_m2_contract_units' WHERE migration = '2026_03_11_000001_backfill_total_area_m2_contract_units';
UPDATE migrations SET migration = '2026_03_11_000006_replace_lat_lng_with_location_url_contract_infos' WHERE migration = '2026_03_11_000002_replace_lat_lng_with_location_url_contract_infos';
UPDATE migrations SET migration = '2026_03_17_000004_create_ai_interaction_logs_table' WHERE migration = '2026_03_17_000001_create_ai_interaction_logs_table';
```

Run once per environment after pulling the rename.

## `ai_interaction_logs` re-run safety

`2026_03_17_000004_create_ai_interaction_logs_table` skips `Schema::create` if the table already exists, so environments that already ran the migration under the old filename will not fail when the new filename is applied once.
