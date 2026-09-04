# COLM Registrar - Workflow State Machine

The document request processing pipeline is governed by a strict state machine (`RequestWorkflowService::ALLOWED_TRANSITIONS`).

## Valid Status States
1. `PENDING PAYMENT` - Initial state after student submission; payment is in person only.
2. `PAID` - Cashier payment confirmed by authorized Registrar/Personnel staff.
3. `FOR PROCESSING` - Paid request waiting for staff to start work.
4. `PROCESSING` - Registrar personnel is preparing the document (printing, signing).
5. `FOR REVIEW/APPROVAL` - Optional approval step when required by the document policy.
6. `READY FOR RELEASE` - Document is complete and ready for pickup/delivery.
7. `RELEASED` - Document handed to the student or authorized representative.
8. `COMPLETED` - Release successfully recorded; terminal state.
9. `FOR CORRECTION` - Reverted if errors are found during processing.
10. `ON HOLD` - Suspended due to clearance issues or disciplinary holds.
11. `REJECTED/CANCELLED` - Terminal state for invalid or withdrawn requests.

## Workflow Rules
- Students cannot change status.
- Personnel can change status but are strictly bound by the allowed matrix (e.g., cannot jump from `REQUEST SUBMITTED` to `RELEASED`).
- Students cannot mark payment as paid; authorized staff confirm `CASH / IN-PERSON` payment.
- Payment confirmation automatically advances `PENDING PAYMENT` -> `PAID` -> `FOR PROCESSING`.
- Reaching `RELEASED` automatically advances the request to `COMPLETED` after identity verification and release recording.
- Reaching `REJECTED/CANCELLED` automatically zeroes out pending payments.
- Overdue tracking relies on `target_completion_date` calculated automatically based on `documents.processing_days` excluding weekends.
