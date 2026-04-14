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
  cities_only_import.csv           — city_name, city_code only (same API as cities_with_districts; omit district_name column).
  cities_with_districts_import.csv — city_name, city_code, district_name (optional). Sample codes RYD-IMP / JED-IMP pair with contracts_import when using city_code+district_name.
  districts_import.csv             — city_id, name — replace city_id with a real cities.id from your DB (sample uses 1).
  teams_import.csv                 — name, code (optional), description (optional)
  employees_import.csv             — see header row
  contracts_import.csv             — same fields as POST /contracts body: developer_*, city_id, district_id, side,
                                    contract_type, project_*, developer_requiment, notes, commission_*. Column units_json
                                    holds the same JSON array as body key `units` (type, count, price). Sample uses 1,1
                                    and 2,2 after a fresh import of cities_with_districts_import.csv (otherwise set real IDs).
                                    Alternative columns city_code + district_name are also supported by the importer.
  contract_info_import.csv         — optional contract / second-party fields
  second_party_data_import.csv     — URLs + advertiser_section_url (digits only)
  contract_units_import.csv        — POST …/contracts/units/upload-csv/{contractId}; field csv_file. Headers: unit_type,
                                    unit_number, price (required); optional status, area, floor, bedrooms, bathrooms,
                                    private_area_m2, view (stored as facade), description_en/ar, diagrames.
