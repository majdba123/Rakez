# Postman API Examples

This document contains **sanitized, illustrative examples** for local/API testing. It intentionally avoids real personal contact data, production credentials, reusable access tokens, and live infrastructure values.

For the broader endpoint inventory, use the maintained Postman collection under `docs/` and verify routes against `routes/api.php` before relying on an example as a permanent contract.

## Authentication

Protected endpoints require an authenticated Sanctum token:

```http
Authorization: Bearer <access-token>
Accept: application/json
Content-Type: application/json
```

Never commit real bearer tokens into documentation or Postman environments.

## Example: Create a contract

```http
POST http://localhost/api/contracts/store
```

```json
{
  "project_name": "مشروع تجريبي",
  "developer_name": "شركة تطوير تجريبية",
  "developer_number": "DEV-DEMO-001",
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
      "count": 1,
      "price": 1500000
    }
  ]
}
```

Expected success responses should be verified against the current controller/resource implementation and automated tests rather than copied from historical snapshots.

## Example: Fetch a contract

```http
GET http://localhost/api/contracts/{id}
```

Illustrative response fragment:

```json
{
  "data": {
    "id": 1,
    "project_name": "مشروع تجريبي",
    "status": "pending",
    "info": {
      "first_party_name": "شركة راكز العقارية",
      "first_party_phone": "<configured-company-phone>",
      "first_party_email": "<configured-company-email>",
      "second_party_name": "عميل تجريبي",
      "second_party_phone": "0500000000",
      "second_party_email": "customer@example.test"
    }
  }
}
```

First-party business contact values are deployment/business configuration and must not be populated from a developer's personal contact details in public source documentation.

## Example: Store contract information

```http
POST http://localhost/api/contracts/{id}/store-info
```

```json
{
  "second_party_name": "عميل تجريبي",
  "second_party_id": "1000000000",
  "second_party_phone": "0500000000",
  "second_party_email": "customer@example.test"
}
```

## Example: Update contract status

```http
PUT http://localhost/api/contracts/{id}/update-status
```

```json
{
  "status": "approved"
}
```

Use only status values currently accepted by validation/domain logic.

## Example data policy

- Use `.test` email domains and clearly fake phone/identity values in public examples.
- Do not copy production customer, employee, signatory, banking, or contact data into fixtures/docs.
- Do not commit access tokens, API keys, cookies, passwords, private hosts, or raw production IPs.
- Keep environment-specific values in `.env`, GitHub Secrets, or provider-managed configuration.
- If a real credential has ever entered Git history, rotate/revoke it externally; editing this document does not remove historical exposure.

See [`SECURITY.md`](../SECURITY.md) for the repository reporting and credential-handling policy.
