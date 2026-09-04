# COLM Registrar - Database Architecture

The system utilizes MySQL 8.0+ / MariaDB 10.4+ with InnoDB for strict referential integrity.

## Core Tables

### 1. `users`
- Stores authentication credentials (`password_hash` using bcrypt).
- RBAC roles: `Student`, `Personnel`, `Registrar`, `Admin`.

### 2. `students` & `enrollment_records` & `academic_records`
- Student master profile (`student_number`, `first_name`, etc.).
- Normalized history of enrollments (`academic_year`, `semester`, `program`).
- Basic academic standing (GPA, total units).

### 3. `documents` & `document_requirements`
- Document Catalog.
- Strict mapping of what requirements a student must upload per document type.

### 4. `document_requests` (The core transaction table)
- Tracks the physical request.
- Generates `tracking_number` via `tracking_sequences` atomic locking.
- Stores `current_status`, `amount_due`, `payment_status`.

### 5. `request_requirements`
- Mapping of the files a student uploaded for a specific request.
- Verification status controlled by Personnel.

### 6. `request_status_logs`
- Immutable timeline of state transitions for a request.

### 7. `payments`
- Financial tracking. Students submit proofs, Personnel verify.

### 8. `document_releases`
- Audit trail for the physical or digital release of a document (recipient type, ID verification flag).

### 9. `notifications`
- In-app toast/bell notifications system.

### 10. `audit_logs`
- Global immutable technical audit trail.
