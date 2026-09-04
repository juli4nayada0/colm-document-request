# COLM Registrar - REST API Documentation

Base URL: `/api/v1`
Authentication: Session cookies + `X-CSRF-Token` header.

## Authentication Endpoints
- `POST /auth/login` - Authenticate user (username, password)
- `POST /auth/logout` - Destroy session
- `GET /auth/me` - Get current session context

## Document Catalog
- `GET /documents` - List active documents
- `GET /documents?all=1` - List all documents (Registrar only)
- `GET /documents/:id` - Get document details
- `POST /documents` - Create new document type
- `PUT /documents/:id` - Update document type
- `PATCH /documents/:id/status` - Toggle active/inactive

## Document Requests
- `GET /requests` - List requests (supports filters, pagination, role-based scoping)
- `GET /requests/:id` - Get full request details (info, timeline, requirements, payment)
- `POST /requests` - Submit new request (Multipart/form-data for requirement files)
- `GET /requests/track/:tracking_number` - Public tracking endpoint (no auth required)
- `PATCH /requests/:id/status` - Transition request state
- `POST /requests/:id/assign` - Assign processing personnel
- `POST /requests/:id/payment` - Submit proof of payment
- `POST /requests/:id/release` - Record document release

## Requirements & Payments Verification
- `PATCH /requests/requirements/:id/verify` - Verify/reject uploaded requirement
- `PATCH /payments/:id/verify` - Verify/reject payment proof

## Students & Import
- `GET /students` - List students
- `GET /students/:id` - Get student profile & enrollment history
- `POST /students/import` - Two-stage CSV bulk import (`action=preview` | `action=commit`)

## Users & Administration
- `GET /users` - List users
- `POST /users` - Create user
- `PUT /users/:id` - Edit user
- `PATCH /users/:id/status` - Toggle user active status

## Reports
- `GET /reports/summary` - KPI summary
- `GET /reports/daily` - Daily requests
- `GET /reports/monthly` - Monthly breakdown
- `GET /reports/document-types` - By document type
- `GET /reports/programs` - By academic program
- `GET /reports/pending` - Active queue
- `GET /reports/completed` - Completed / ready
- `GET /reports/released` - Released claiming
- `GET /reports/cancelled` - Cancelled / rejected
- `GET /reports/personnel` - Staff workload
- `GET /reports/processing-time` - Turnaround times
- `GET /reports/overdue` - Overdue metrics
- `GET /reports/payments` - Collection verification
- `GET /reports/export?type=...` - Download CSV stream

## Files & Media
- `GET /files/download?type={requirement|payment}&id={id}` - Authenticated streaming of private files.
