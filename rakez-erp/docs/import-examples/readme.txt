Rakez ERP — import templates (CSV)
===================================

These files are plain UTF-8 CSV. They are not native Excel (.xlsx) files.

From Microsoft Excel:
  File → Save As → choose "CSV UTF-8 (Comma delimited) (*.csv)".

Rules that apply to API imports (summary):
  • Any validation error in a file: the import is marked failed; fix the file and upload again.
  • Any error while saving rows (database / business rules): the whole batch is rolled back — no partial import.
  • Cities import: each city_code must appear only once in the same file (duplicates are rejected).
  • Cities + districts: optional column district_name; same city_code must not repeat in the file.
  • Districts-only import: columns city_id, name — duplicate (city_id + same district name) in the file is rejected.
  • Re-uploading rows that already exist in the database may show successful_rows=0 and skipped_rows>0 (no overwrite).

File list:
  cities_only_import.csv           — city_name, city_code
  cities_with_districts_import.csv — city_name, city_code, district_name (optional)
  districts_import.csv             — city_id, name
  teams_import.csv                 — name, code (optional), description (optional)
  employees_import.csv             — see header row
  contracts_import.csv             — one row = one contract; required column units_json = JSON array for `units`
                                    (objects with type, count, price). Quote the cell in CSV/Excel. After the contract
                                    exists, extra inventory lines can use contract_units_import.csv.
  contract_info_import.csv         — optional contract / second-party fields
  second_party_data_import.csv     — URLs + advertiser_section_url (digits only)
  contract_units_import.csv        — per-contract units (API may differ from bulk csv_imports)
