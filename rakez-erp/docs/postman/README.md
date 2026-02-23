# RAKEZ ERP – Postman Collections

Import these collections and the environment into Postman to call the Accounting and Credit APIs.

## Collections

| Collection | File | Description |
|------------|------|-------------|
| **Accounting (Complete)** | `collections/RAKEZ_ERP_Accounting_Complete.postman_collection.json` | All accounting endpoints: Dashboard, Notifications, Sold Units & Commissions, Deposits, Salaries, Legacy confirmations, Developers. |
| **Credit (Complete)** | `collections/RAKEZ_ERP_Credit_Complete.postman_collection.json` | All credit endpoints: Dashboard, Notifications, Bookings (confirmed/negotiation/waiting/sold/cancelled), Financing, Title Transfer, Claim Files, Payment Plan. |

## Environment

- **Rakez ERP - Local**: `environments/Rakez-ERP-Local.postman_environment.json`  
  Set `base_url` (e.g. `http://localhost:8000/api`), `user_email`, `user_password`. After **Login**, `auth_token` is set automatically.

## How to use

1. **Import**  
   Postman → Import → choose the collection JSON(s) and the environment JSON.

2. **Select environment**  
   Top-right: select **Rakez ERP - Local** (or your copy).

3. **Login**  
   Run **00 - Auth → Login**. This sets `auth_token` in the environment.

4. **Set IDs**  
   For Accounting: set `reservation_id`, `commission_id`, `deposit_id`, `notification_id`, `distribution_id`, `employee_id` from list responses.  
   For Credit: set collection variables `booking_id`, `transfer_id`, `claim_file_id`, `notification_id`, `stage_number`, `installment_id` from list/detail responses (Collection → Variables).

## Base URL

- No trailing slash: `http://localhost:8000/api`
- All request URLs in the collections use `{{base_url}}` + path.

## Auth

- Collections use **Bearer Token** with `{{auth_token}}`.
- Login request uses no auth and saves `access_token` into `auth_token`.

## Quick reference

- **Accounting**: ~28 requests (Auth, Dashboard, Notifications, Sold Units & Commissions, Deposits, Salaries, Legacy, Developers).
- **Credit**: ~35 requests (Auth, Dashboard, Notifications, Bookings, Financing, Title Transfer, Claim Files, Payment Plan).
