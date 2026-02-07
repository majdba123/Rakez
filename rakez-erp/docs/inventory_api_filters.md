## Inventory API filters (for frontend)

These inventory endpoints accept filters as **query parameters (GET)**.

### Endpoints

- `GET /api/inventory/contracts/agency-overview`
- `GET /api/inventory/contracts/locations`

Both endpoints share the same filter fields.

### Filter fields (query parameters)

- **status**: string, optional  
  Allowed: `pending`, `approved`, `rejected`, `completed`, `ready`

- **user_id**: integer|string, optional  
  Filter by contract owner (creator).

- **city**: string, optional  
  Exact match on `contracts.city`.

- **district**: string, optional  
  Exact match on `contracts.district`.

- **project_name**: string, optional  
  Partial match on `contracts.project_name` (LIKE `%value%`).

- **has_photography**: `1` or `0` (or `true`/`false`), optional  
  - `1`: only contracts that have a photography department record (not soft-deleted)  
  - `0`: only contracts that do NOT have a photography department record (or it is soft-deleted)

- **has_montage**: `1` or `0` (or `true`/`false`), optional  
  Same behavior as `has_photography` but for montage.

- **per_page**: integer, optional  
  - `agency-overview`: 1..200 (default 50)  
  - `locations`: 1..500 (default 200)

### Color logic (agency overview)

`color` is computed from the remaining time until `contract_infos.agency_date`:

- **green**: remainingDays > 90 (more than 3 months)
- **yellow**: remainingDays > 30 and <= 90 (between 1 and 3 months)
- **red**: remainingDays <= 30 (less than 1 month) OR already expired


