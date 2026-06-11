# SplitBill JSON API v1

> Versioned JSON API used by the Flutter mobile client.
> Web (Livewire) and API call the **same Actions** — same business rules apply.

- **Base URL (local):** `http://localhost:8000/api/v1`
- **Auth:** Bearer token (Laravel Sanctum). Send `Authorization: Bearer <token>`.
- **Money:** All amounts are **integer rupiah** (no decimals). Responses also include a
  `*_formatted` field (e.g. `"Rp50.000"`) for direct display.
- **Dates:** ISO-8601 strings. `expense_date` is a calendar date (`YYYY-MM-DD`).
- **Error shape:**
  ```json
  { "message": "Friend code not found." }
  ```
  Validation errors:
  ```json
  { "message": "The given data was invalid.", "errors": { "email": ["..."] } }
  ```

## Status codes
| Code | Meaning |
|------|---------|
| 200  | OK |
| 201  | Created |
| 204  | No content (delete) |
| 401  | Unauthenticated — missing/invalid token |
| 403  | Forbidden — authenticated but not allowed (policy denied) |
| 404  | Resource not found |
| 422  | Validation or domain rule violation |
| 429  | Rate-limit exceeded (auth endpoints: 6/min/IP; friend-code lookup: 10/min/user) |

---

## Auth

### POST `/register`  *(public, rate-limited 6/min)*
```json
{ "name": "Alice", "email": "a@x.test", "password": "rahasia123", "device_name": "iPhone 15" }
```
→ `201 Created`
```json
{
  "data": { "id": 1, "name": "Alice", "friend_code": "ABCD1234", "email": "a@x.test" },
  "token": "1|plaintext-token-..."
}
```

### POST `/login`  *(public, rate-limited 6/min)*
```json
{ "email": "a@x.test", "password": "rahasia123", "device_name": "iPhone 15" }
```
→ `200 OK` — same shape as `/register`. `422` on bad credentials.

### POST `/logout`  *(auth)*
Revokes the **current** access token. Other tokens for this user are untouched.
→ `200 OK { "message": "Logged out." }`

### GET `/me`  *(auth)*
→ `200 OK { "data": { id, name, friend_code, email } }`

---

## Friends

### GET `/friends`  *(auth)*
Returns the authenticated user's accepted friends.
→ `200 OK { "data": [ { "id", "name", "friend_code" }, ... ] }`

### GET `/friends/requests`  *(auth)*
→ `200 OK`
```json
{
  "incoming": [ { "id", "status": "pending", "sender": {...}, "receiver": {...}, "sender_id", "receiver_id", "responded_at", "created_at" } ],
  "sent":     [ ... ]
}
```

### POST `/friends/requests`  *(auth, rate-limited 10/min/user)*
```json
{ "friend_code": "ABCD1234" }
```
→ `201 Created` with the new pending request. `422` on unknown code, self-add, already
friends, duplicate pending, or previously declined. `429` once the per-user lookup
budget is exhausted (so friend codes can't be enumerated).

### POST `/friends/requests/{id}/accept`  *(auth, receiver only)*
→ `200 OK` with the accepted request. `403` if you are not the receiver.

### POST `/friends/requests/{id}/decline`  *(auth, receiver only)*
→ `200 OK` with the declined request. `403` if you are not the receiver.

---

## Groups

### GET `/groups`  *(auth)*
Lists groups the user belongs to.

### POST `/groups`  *(auth)*
```json
{
  "name": "Trip Bali",
  "description": "Liburan akhir tahun",  // optional
  "member_ids": [12, 17]                  // optional, must all be friends of the caller
}
```
→ `201 Created` with the group including `members[]`.
`422` if any `member_ids` is not a friend.

### GET `/groups/{group}`  *(auth, members only)*
→ `200 OK` group with eager-loaded `members[]`.
`403` if not a member.

### POST `/groups/{group}/members`  *(auth, owner only)*
```json
{ "user_id": 17 }
```
→ `201 Created` with the refreshed group. `403` if not owner; `422` if not a friend.

### DELETE `/groups/{group}/members/{user}`  *(auth, owner only)*
→ `204 No Content`. Cannot remove the owner. `403`/`422` otherwise.

---

## Expenses

### GET `/groups/{group}/expenses`  *(auth, members only)*
→ `200 OK { "data": [ ExpenseResource, ... ] }`

### POST `/groups/{group}/expenses`  *(auth, members only)*
```json
{
  "description": "Makan siang",
  "total_amount": 90000,            // integer rupiah
  "split_method": "equal",          // "equal" | "exact" | "percent"
  "expense_date": "2026-05-27",     // YYYY-MM-DD
  "payer_id": 1,                    // must be a group member
  "participant_ids": [1, 2, 3],
  "shares": { "1": 60, "2": 40 }    // required for "exact" (rupiah) and "percent" (0..100)
}
```
→ `201 Created` with `ExpenseResource` (incl. `participants[]`).
For `exact`, shares must sum to `total_amount`. For `percent`, percentages must sum to 100;
rounding remainder is added to the lowest `user_id`. `422` for any rule violation.

**ExpenseResource shape:**
```json
{
  "id": 1, "group_id": 1, "payer_id": 1,
  "description": "Makan siang",
  "total_amount": 90000,
  "total_amount_formatted": "Rp90.000",
  "split_method": "equal",
  "expense_date": "2026-05-27",
  "participants": [
    { "user_id": 1, "share_amount": 30000, "share_amount_formatted": "Rp30.000" }
  ],
  "created_at": "..."
}
```

---

## Balances & settlements

### GET `/groups/{group}/balances`  *(auth, members only)*
Returns each member's net balance plus the minimal set of transfers to clear it.
→ `200 OK`
```json
{
  "balances": [
    { "user_id": 1, "name": "Alice", "balance": 60000, "balance_formatted": "Rp60.000" },
    { "user_id": 2, "name": "Bob",   "balance": -30000, "balance_formatted": "-Rp30.000" }
  ],
  "transfers": [
    { "from": 2, "to": 1, "amount": 30000, "amount_formatted": "Rp30.000" }
  ]
}
```
Sign convention: **positive = others owe this user**, negative = this user owes others.
The sum of all `balance` is always 0.

### POST `/groups/{group}/settlements`  *(auth, members only)*
```json
{ "from_user_id": 2, "to_user_id": 1, "amount": 30000 }
```
→ `201 Created` with `SettlementResource`. The caller must be either `from` or `to`.
Partial settlements are allowed.

---

## Notes for Flutter clients
- Pass a stable `device_name` per device so you can manage tokens per device later.
- Persist the bearer token (e.g. secure storage). Treat 401 as "log out and re-login".
- The same Actions back this API and the web UI; behavior is identical.
- Pagination is **not yet** wired (BL candidate). Lists currently return full collections.
