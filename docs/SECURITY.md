# COLM Registrar - Security Architecture

## 1. Authentication & Session Management
- `AuthMiddleware` verifies PHP standard sessions.
- Passwords use bcrypt hashing algorithm (`password_hash($pw, PASSWORD_BCRYPT)`).
- Hardcoded brute-force rate-limiting on login (Max 5 attempts / 15 minutes).

## 2. Role-Based Access Control (RBAC)
- `RoleMiddleware` intercepts routes based on allowed roles.
- APIs return HTTP 403 Forbidden if the session role is insufficient.

## 3. Cross-Site Request Forgery (CSRF)
- `CsrfMiddleware` forces all `POST`, `PUT`, `PATCH`, `DELETE` requests to provide an `X-CSRF-Token` header.
- Tokens are automatically rotated and injected into `GET` responses to refresh the frontend.

## 4. Separation of Duties (SoD)
- System Administrators (`Admin`) possess full access to `/users` and `/audit-logs`.
- However, they are strictly blocked from `/requests`, `/documents`, `/reports`.
- This ensures technical IT staff cannot alter or view confidential academic transcripts.

## 5. Private File Storage
- User uploads (Requirements & Payment Proofs) are NOT stored in the public `frontend/` folder.
- Stored in `backend/storage/private/...`.
- Served dynamically via `/api/v1/files/download` which runs Auth and RBAC checks before streaming file binaries via PHP `readfile()`.
- Apache `.htaccess` blocks direct URL resolution to storage folders.

## 6. SQL Injection Prevention
- Entire system strictly uses `PDO` Prepared Statements.
- Dynamic query building (`Filter / Search`) safely passes values as bound parameters `?`.

## 7. XSS Prevention
- All frontend DOM injections utilize `utils.escapeHtml()` to neutralize executable script tags before rendering.
