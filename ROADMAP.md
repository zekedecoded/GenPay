# GJC EduPay Fresh Build Roadmap

This roadmap assumes the system will be rebuilt from scratch. The existing project can still be used as a reference for business rules, page ideas, and lessons learned, but the new build should start with a cleaner database, clearer transaction model, stronger security, and a more polished user experience.

## Product Goal

Build a secure school wallet system for students, merchants, visitors, cashiers, finance staff, and administrators. The system should support QR payments, wallet top-ups, merchant encashments, visitor vouchers, refund handling, finance analytics, and future third-party payment integrations such as GCash or bank transfers.

## Core User Roles

- Student: views wallet balance, hides/shows balance, scans merchant QR codes, pays, views itemized history, requests top-ups.
- Merchant: generates payment QR codes, records item/category sales, accepts visitor vouchers, views sales, requests encashment.
- Visitor: receives a same-day QR voucher, uses it on campus, refunds unused balance before leaving.
- Cashier/Finance Staff: approves top-ups, issues vouchers, processes refunds, handles encashments, reconciles funds.
- Admin: manages users, roles, merchants, policies, reports, system settings, and audit records.
- Super Admin: controls high-risk settings such as circulation cap, payment gateway credentials, and system-wide permissions.

## Phase 1: Planning And Requirements

1. Finalize business rules.
   - Decide wallet limits, daily spending limits, refund rules, voucher expiry time, and who can approve financial actions.
   - Define whether visitor vouchers are always refundable before leaving or only conditionally refundable.
   - Decide how manual cashier top-ups and future online top-ups should coexist.

2. Define transaction types.
   - Student top-up.
   - Student purchase.
   - Visitor voucher issue.
   - Visitor voucher payment.
   - Visitor refund.
   - Merchant encashment.
   - Adjustment/reversal.
   - Third-party payment top-up.

3. Define success criteria.
   - Every peso movement is auditable.
   - No duplicate QR payment can be charged.
   - Students can understand what they bought, not only the amount deducted.
   - Finance can see item demand, sales trends, refunds, encashments, and total wallet movement.

## Phase 2: Technology And Architecture

1. Recommended architecture.
   - Backend: PHP with a structured MVC pattern, or Laravel if allowed.
   - Database: MySQL/MariaDB.
   - Frontend: server-rendered pages with Bootstrap or a consistent design system.
   - Charts: Chart.js for analytics.
   - QR: server-created QR payment intents, not client-only JSON payloads.

2. Core modules.
   - Authentication and role permissions.
   - Wallet ledger.
   - Student portal.
   - Merchant portal.
   - Cashier/admin portal.
   - Visitor voucher module.
   - Analytics/reporting module.
   - Notification/logging module.
   - Future payment gateway module.

3. Build principle.
   - Use an append-only ledger for all financial movement.
   - Never directly edit balances without creating a ledger record.
   - Use transactions and row locks for wallet updates.
   - Keep display text separate from financial audit data.

## Phase 3: Database Design

1. Main tables.
   - `users`: account, role, status, contact details.
   - `student_profiles`: student metadata.
   - `merchant_profiles`: merchant/store metadata.
   - `wallets`: wallet owner, wallet type, current balance, status.
   - `ledger_entries`: immutable money movement records.
   - `transactions`: user-facing transaction summary.
   - `transaction_items`: itemized purchase lines.
   - `payment_intents`: server-generated QR payment requests.
   - `topup_requests`: manual and future online top-up requests.
   - `encashment_requests`: merchant withdrawal requests.
   - `visitor_vouchers`: temporary visitor QR balances.
   - `voucher_refunds`: refund processing records.
   - `audit_logs`: admin/security actions.
   - `system_settings`: policies and finance controls.

2. Transaction metadata.
   - Store item name, category, quantity, unit price, merchant snapshot, student snapshot, and reference number.
   - Categories should include Food, Uniform, School Supplies, Printing, Books, Events, and Other.

3. Financial safety.
   - Use decimal money fields.
   - Add unique references and idempotency keys.
   - Add indexes for date, user, merchant, category, status, and reference number.

## Phase 4: Authentication And Access Control

1. Build login/logout/session handling.
2. Add role-based middleware for Student, Merchant, Cashier, Finance, Admin, and Super Admin.
3. Add password change and recovery flows.
4. Add account status controls: active, suspended, blocked, restricted.
5. Add CSRF protection to all forms and mutating APIs.

## Phase 5: Student Portal

1. Dashboard.
   - Current wallet balance.
   - Privacy Toggle for Wallet Balance using an eye/eye-off icon.
   - Quick actions: Scan & Pay, Top-Up, History, Profile.

2. Scan & Pay.
   - Scan merchant QR.
   - Show merchant, item, category, amount, and expiry before payment.
   - Confirm payment.
   - Prevent duplicate or expired QR charges.

3. Transaction History.
   - Itemized Transaction Descriptions.
   - Show what was purchased, category, merchant, amount, date, and reference.
   - Filters for date, type, category, and merchant.

4. Top-Up Request.
   - Manual cashier top-up first.
   - Prepare UI and database fields for future GCash/bank transfer top-ups.

## Phase 6: Merchant Portal

1. Merchant dashboard.
   - Sales today.
   - Current collected balance.
   - Pending encashments.
   - Recent transactions.

2. Generate QR.
   - Merchant enters item name, category, price, and optional quantity.
   - Server creates a payment intent with expiry.
   - QR contains only a secure intent token/reference.

3. Visitor Voucher Scanner.
   - Scan visitor QR.
   - Validate same-day expiry.
   - Show visitor remaining balance.
   - Charge amount and record item/category where applicable.

4. Sales History.
   - Real ledger-backed sales list.
   - Item/category breakdown.
   - Export option.

5. Encashment.
   - Request payout.
   - Track pending, approved, released, rejected status.

## Phase 7: Visitor Voucher System

1. Voucher creation.
   - Cashier/admin enters visitor name, contact, amount, and refund eligibility.
   - Voucher expires within the same day.
   - Print QR voucher with expiry time and refund instructions.

2. Voucher usage.
   - Merchant scans voucher.
   - System validates QR, expiry, status, and balance.
   - Payment deducts voucher balance and credits merchant wallet.

3. Voucher refund.
   - Visitor returns to admin/cashier office before leaving campus.
   - Staff searches/scans voucher.
   - System shows unused balance.
   - Staff confirms refund and records processor, notes, timestamp, and receipt number.
   - Voucher is closed after refund.

4. End-of-day handling.
   - Expire all active same-day vouchers.
   - Produce visitor voucher summary: issued, spent, refunded, expired unused balance.

## Phase 8: Admin And Finance Portal

1. Admin dashboard.
   - Total users.
   - Active students/merchants/visitors.
   - Pending top-ups.
   - Pending encashments.
   - Today's transaction volume.

2. Admin/Finance Analytics Dashboard.
   - Bar graph: daily sales.
   - Pie/doughnut chart: sales by category.
   - Line chart: top-up vs spending trend.
   - Bar chart: top merchants.
   - Table: most demanded items/categories.
   - KPI cards: total sales, refunds, encashments, active balances, voucher balances.

3. User management.
   - Add/edit users.
   - Assign roles.
   - Suspend/block/restrict accounts.
   - Set spending limits if required.

4. Finance operations.
   - Approve/reject top-ups.
   - Release/reject encashments.
   - Process visitor refunds.
   - Export transactions and analytics reports.

## Phase 9: Third-Party Payment Integration Future Scope

1. Prepare provider-ready top-ups.
   - Add provider, provider reference, provider status, callback payload, verified timestamp, and idempotency key.

2. GCash/bank transfer flow.
   - Student starts top-up.
   - System redirects to payment provider or displays payment instructions.
   - Provider sends webhook.
   - System verifies webhook signature.
   - Wallet is credited only after verified payment.

3. Reconciliation.
   - Match provider payments against credited wallet top-ups.
   - Flag mismatches.
   - Export finance reports.

## Phase 10: Security, QA, And Deployment

1. Security.
   - CSRF protection.
   - Server-side QR signing.
   - Session hardening.
   - Rate limiting for login and payment APIs.
   - Audit logs for admin and finance actions.

2. Testing.
   - Unit tests for wallet movement.
   - Integration tests for payment, top-up, encashment, voucher, and refund flows.
   - Manual QA for each role.
   - Mobile testing for scanner pages.

3. Deployment readiness.
   - Database migration scripts.
   - Backup and restore procedure.
   - Environment config for local, staging, and production.
   - Admin guide and cashier/merchant/student quick guides.

## Suggested Build Order

1. Database schema and wallet ledger.
2. Authentication and role access.
3. Student wallet dashboard with privacy toggle.
4. Manual cashier top-up flow.
5. Merchant QR payment intents.
6. Student scan-and-pay with itemized transaction history.
7. Merchant sales history and encashments.
8. Visitor voucher issue, payment, same-day expiry, and refund.
9. Admin/finance analytics dashboard.
10. Exports, reports, polish, and QA.
11. Future GCash/bank transfer integration.

## Minimum Viable Version

The first usable version should include login, student wallets, manual top-ups, merchant QR payments, itemized transaction history, basic admin user management, and transaction reports.

## Polished Version

The polished version should include balance privacy toggle, server-signed QR intents, visitor voucher expiry/refunds, finance analytics charts, encashment workflow, exports, role restrictions, audit logs, responsive UI, and clean documentation.

## Future Version

The future version should include GCash or bank transfer top-ups, webhook verification, automated reconciliation, notifications, spending limits, and more advanced finance forecasting.
