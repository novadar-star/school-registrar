# COJ Catholic Progressive School
## Enrollment and Records Management System (ERMS)
### System Documentation — Group 5, Adamson University

---

## 1. SYSTEM OVERVIEW

COJ Catholic Progressive School is a private Catholic institution offering Preschool, Elementary, and Junior High School education. This system replaces the school's previous manual workflow — Google Forms for enrollment, spreadsheets for records, and physical folders for documents — with a centralized, web-based Enrollment and Records Management System.

**System Name:** COJ ERMS — Enrollment and Records Management System
**Tech Stack:** PHP 8+, MySQL/MariaDB, HTML/CSS, Vanilla JS, Bootstrap Icons, PHPMailer
**Deployment:** XAMPP (local) / Railway (cloud-capable via environment variables)
**Database:** `school_registrar` — 21 tables

---

## 2. STATEMENT OF THE PROBLEM

| # | Problem | Impact |
|---|---|---|
| 1 | Enrollment via Google Forms with no database backend | Unstructured data, prone to loss, no real-time visibility |
| 2 | No centralized student records system | Staff manually search spreadsheets; errors are common |
| 3 | Payment tracking done manually per student | No real-time balances or collection totals |
| 4 | Document requirements tracked on paper | No visibility on missing documents per student |
| 5 | Parents have no way to check enrollment status | Constant phone calls to the registrar's office |
| 6 | No role-based access | All staff share the same files with no accountability |
| 7 | No data backup mechanism | Risk of permanent data loss from hardware failure |
| 8 | No automated parent communication | Parents unaware of document rejections or enrollment updates |

---

## 3. OBJECTIVES

### General Objective
To design and develop a web-based Enrollment and Records Management System for COJ Catholic Progressive School that centralizes student data, streamlines enrollment processing, and provides a self-service portal for parents.

### Specific Objectives
1. Implement a normalized relational database (MySQL) to store and manage student, enrollment, payment, and document records
2. Develop a CRUD interface for student records with search, filter, and pagination
3. Build an online enrollment form that replaces Google Forms and writes directly to the database
4. Create a payment tracking module with fee breakdown, installment schedules, and balance tracking
5. Implement a document requirements tracker with upload, verify, reject, and to-follow workflow
6. Provide a parent-facing portal for enrollment monitoring, document submission, payment scheme selection, and SOA viewing
7. Enforce role-based access control (Superadmin, Registrar, Finance) for data accountability
8. Send automated email notifications to parents on key enrollment events
9. Enable database backup and CSV export for data portability and disaster recovery
10. Implement brute-force login protection for both admin and parent accounts

---

## 4. SCOPE AND LIMITATIONS

### In Scope

| Module | Description |
|---|---|
| **Student Records** | Full CRUD — add, edit, view, search, filter, archive students with LRN, grade, section, personal info |
| **Online Enrollment Form** | Public form at `home.php`; creates student + parent account; supports multi-child enrollment |
| **Enrollment Management** | Admin processes applications: Pending → Enrolled / Dropped; walk-in enrollment by registrar |
| **Fee Structure** | Define fees per grade level and school year (Tuition, Miscellaneous, PTA, Development, Books, SPED, Reservation, Other) |
| **Discounts & Scholarships** | Percentage or fixed-amount discounts per student (Employee, Sibling, Scholarship, Other) |
| **Payment Scheme Selection** | Parent selects Annual, Semi-Annual, Quarterly, or Monthly plan; generates installment schedule with due dates |
| **Payment Tracking** | Record payments with OR number, method, date; track balances; view SOA per student |
| **Late Penalty** | ₱500 penalty auto-applied to overdue installments past their due date |
| **Document Requirements** | Track submission status per student; verify, reject (with reason), upload on behalf, mark received, mark to-follow |
| **Parent Portal** | Parents view enrollment timeline, upload documents, select payment scheme, view SOA, upload proof of payment |
| **Email Notifications** | Automated emails via Gmail SMTP (PHPMailer) for: enrollment received, enrollment confirmed, document verified/rejected, payment received, custom admin messages |
| **In-System Notifications** | Bell notifications for admin staff and parents on key events |
| **Reports & Export** | Enrollment and payment summaries; CSV export of student records |
| **User Management** | Superadmin creates and manages staff accounts with role-based access |
| **Brute-Force Protection** | 5 failed login attempts locks account for 15 minutes (both admin and parent portals) |
| **Database Backup** | Full SQL dump export via admin panel |
| **Audit Log** | Every sensitive admin action logged with user name, action, and timestamp |
| **Security Hardening** | Parameterized queries throughout, server-side MIME validation for uploads, `.htaccess` blocking PHP execution in uploads directory, session regeneration on login, input validation and sanitization |

### Out of Scope (Limitations)

| Limitation | Reason / Notes |
|---|---|
| **No academic records (grades/GWA)** | Grading and academic performance tracking is outside the registrar's scope for this project |
| **No attendance tracking** | Attendance management requires integration with class schedules; planned future enhancement |
| **No class scheduling** | Timetable and subject assignment are not part of this system |
| **No mobile application** | System is web-based and desktop-optimized; responsive design is partial |
| **No online payment gateway** | Proof of payment is uploaded manually; GCash/bank transfer processing is not integrated |
| **No SMS notifications** | Requires third-party SMS API (e.g., Semaphore, Vonage); out of scope |
| **No multi-branch / multi-tenant** | Designed for single-school deployment only |
| **No student promotion automation** | Year-end promotion (advancing students to next grade) requires manual re-enrollment |
| **No DepEd LRN integration** | Temp LRN (`P-XXXXXXXXXX`) is assigned at enrollment; official LRN must be manually updated by registrar |
| **No real-time payment verification** | Finance staff manually reviews uploaded receipts; no automated bank reconciliation |
| **Payment scheme is locked once selected** | Parents cannot change their payment scheme after confirmation; requires admin intervention |
| **No parent account creation by admin** | Parent accounts are created through the enrollment form only; registrar cannot manually create them |

---

## 5. USER ROLES AND ACCESS MATRIX

| Feature | Superadmin | Registrar | Finance | Parent |
|---|---|---|---|---|
| Dashboard | ✅ | ✅ | ✅ | ✅ (portal) |
| Students (CRUD) | ✅ | ✅ | ❌ | ❌ |
| Enrollment Management | ✅ | ✅ | ❌ | ✅ (submit only) |
| Requirements Tracker | ✅ | ✅ | ❌ | ✅ (upload only) |
| Fees | ✅ | ✅ | ✅ | ❌ |
| Discounts | ✅ | ✅ | ✅ | ❌ |
| Payments | ✅ | ✅ | ✅ | ✅ (view + upload proof) |
| Payment Scheme | ✅ (view) | ✅ (view) | ✅ (view) | ✅ (select once) |
| SOA | ✅ | ✅ | ✅ | ✅ (own only) |
| Reports & Export | ✅ | ✅ | ✅ | ❌ |
| User Management | ✅ | ❌ | ❌ | ❌ |
| School Years | ✅ | ❌ | ❌ | ❌ |
| Database Backup | ✅ | ❌ | ❌ | ❌ |
| Audit Log (view) | ✅ | ❌ | ❌ | ❌ |
| Reset Payment | ✅ | ✅ | ❌ | ❌ |
| Archive Student | ✅ | ✅ | ❌ | ❌ |
| Export CSV | ✅ | ✅ | ❌ | ❌ |


---

## 6. SYSTEM FLOWCHARTS

### A. PARENT / ENROLLMENT FLOW

```
[Parent] visits home.php (public — no login required)
        │
        ▼
Fills out Online Enrollment Form
  ├── Privacy Consent checkbox (required)
  ├── Student Information (name, grade, birthday, sex, religion)
  ├── Education History (last school, school address)
  ├── Parent/Guardian Information (name, contact, email, address)
  ├── Portal password (min 8 chars, 1 number, 1 special char) — new accounts only
  └── Optional document uploads (Form 138, Good Moral Certificate)
        │
        ▼
Form Submitted (POST to home.php)
        │
        ├──► [DB] Creates parent_accounts record (if new email)
        ├──► [DB] Creates students record (temp LRN: P-XXXXXXXXXX)
        ├──► [DB] Creates parent_student_links record
        ├──► [DB] Creates enrollments record (status: PENDING)
        ├──► [DB] Inserts notifications for all active admin users
        ├──► [EMAIL] Sends enrollment confirmation email to parent (PHPMailer/Gmail)
        └──► Shows confirmation screen with reference number(s)
        │
        ▼
Parent logs into portal/login.php
  · Brute-force protection: 5 failed attempts → 15-minute lockout
  · Session regenerated on successful login
        │
        ▼
Parent Dashboard
  · Enrollment progress timeline: Applied → Documents → Payment → Enrolled
  · Quick links to Requirements, Payment Scheme, Statement of Account
  · Multi-child switcher (if multiple children enrolled)
        │
        ├──► Requirements Page
        │     · View required documents and their status
        │     · Upload documents (JPG, PNG, WEBP, PDF — max 3MB, server-side MIME check)
        │     · Re-upload if rejected (reject reason shown)
        │     · Receive bell notification + email when document is verified or rejected
        │
        ├──► Payment Scheme Page (Step 2 — must complete before SOA)
        │     · Select payment plan: Annual, Semi-Annual, Quarterly, or Monthly
        │     · Plan is locked once confirmed — cannot be changed
        │     · Generates installment schedule with end-of-month due dates
        │     · ₱500 penalty auto-applied to overdue installments
        │
        └──► Statement of Account Page (Step 3 — requires scheme selection)
              · View fee breakdown per grade level with discounts applied
              · View installment schedule with due dates and penalty status
              · Upload proof of payment (JPG, PNG, WEBP, PDF — max 5MB)
              · Cannot re-upload while receipt is pending verification
              · Receive bell notification + email when payment is recorded
```

---

### B. ADMIN / REGISTRAR FLOW

```
[Registrar] logs into index.php
  · Brute-force protection: 5 failed attempts → 15-minute lockout
  · Session regenerated on successful login
        │
        ▼
Dashboard
  · Total students, enrolled count, pending count
  · Total collection amount
  · Students per grade level (bar chart)
  · Recent registrations table
  · Payment summary (paid / partial / unpaid)
        │
        ▼
Enrollment Page
  · Lists all enrollment applications for active school year
  · Filter by status: All / Pending / Enrolled / Dropped
  · Parameterized search by name, LRN, or reference number
        │
        ├── Set ENROLLED → fees auto-assigned to student
        │                → admin bell notification sent
        │                → parent receives congratulations email + bell notification
        │
        └── Set DROPPED  → admin bell notification sent
        │
        ▼
Students Page
  · Full list of all students (paginated, 10 per page)
  · Parameterized search by name or LRN
  · Filter by grade, student type (new/old), school year
  · Add new student manually (CRUD) with server-side validation
  · Edit student details
  · Archive student (soft delete)
  · View student profile (personal info, academic info, documents tab)
        │
        ▼
Requirements Tracker
  · Lists all students with document completion summary
  · Click student → view per-document status
        │
        ├── VERIFY document → parent bell notification + email sent
        ├── REJECT document → parent bell notification + email sent (with reason)
        ├── UPLOAD on behalf of student (admin upload, server-side MIME check)
        ├── MARK RECEIVED (physical document received, auto-verified)
        └── MARK TO FOLLOW (document to be submitted later)
        │
        ▼
Student Profile Page
  · Personal Info tab — full student details
  · Academic tab — grade, section, school year, education history
  · Documents tab — per-document status with verify/reject/upload actions
        │
        ▼
Reports Page
  · Enrollment summary by grade (new vs old, enrolled vs pending)
  · Payment summary by grade (total paid, balance, fully paid count)
  · Export to Excel (.xlsx) via SheetJS
```

---

### C. FINANCE FLOW

```
[Finance] logs into index.php
        │
        ▼
Fees Page (superadmin/registrar access only)
  · Define fee structure per grade level and school year
  · Fee types: Tuition, Miscellaneous, PTA Fund, Development, Books, SPED, Reservation, Other
  · Amount must be greater than zero
  · Add, edit, delete fees
        │
        ▼
Discounts Page (superadmin/registrar access only)
  · Apply discounts per student: Employee (100%), Sibling, Scholarship, Other
  · Percentage clamped to 0–100%; fixed amount supported
  · Automatically deducted from student SOA
        │
        ▼
Payments Page
  · Parameterized search by student name or LRN
  · Lists all students with payment status (Paid / Partial / Unpaid)
  · View parent-uploaded proof of payment
  · Record payment (amount > 0 required, OR number, payment method, date validated)
  · Reset payment — superadmin/registrar only (server-side role check)
        │
        ▼
SOA Page (admin view)
  · Full fee breakdown per student with discounts applied
  · Installment schedule with due dates, penalties, and paid amounts
  · Confirm installment payments with OR number and method
  · Print SOA
```

---

### D. SUPERADMIN FLOW

```
[Superadmin] has access to ALL of the above, PLUS:

Users Page
  · Create staff accounts (Registrar, Finance, Superadmin)
  · Email validated with filter_var; role whitelist enforced server-side
  · Password minimum 8 characters
  · Edit name, email, role, password
  · Activate / Deactivate accounts
  · Unlock locked accounts (after brute-force lockout)

School Years Page
  · Add new school year (format: YYYY-YYYY, validated server-side)
  · Set active school year (all data scopes to this year)

Backup Page
  · Export full database as SQL dump
  · Download for offline storage / disaster recovery
```

---

## 7. EMAIL NOTIFICATION EVENTS

All emails are sent via Gmail SMTP using PHPMailer. The `notify_parent()` helper in `helpers.php` automatically sends both a portal bell notification and an email on every call.

| Trigger | Email Sent To | Subject |
|---|---|---|
| Online enrollment form submitted | Parent | "Enrollment Application Received — REF#XXXX" |
| Enrollment status set to Enrolled | Parent | "🎉 Enrollment Confirmed — [Student Name]" |
| Document verified by registrar | Parent | "Document Verified — [Document Name]" |
| Document rejected by registrar | Parent | "Document Needs Resubmission — [Document Name]" |
| Payment recorded with OR number | Parent | "Payment Received — OR# [Number]" |
| Admin sends manual notification | Parent | Custom title set by admin |

---

## 8. ENROLLMENT STATUS FLOW

```
[Parent submits online form]
        │
        ▼
    PENDING
  (awaiting registrar review)
        │
        ├──► ENROLLED  (registrar approves)
        │     · Fees auto-assigned to student
        │     · Parent receives congratulations email
        │     · Payment scheme selection unlocked
        │
        └──► DROPPED   (rejected or withdrew)
```

## 9. DOCUMENT STATUS FLOW

```
    MISSING
  (default — not yet submitted)
        │
        ├──► SUBMITTED  (parent uploads file — server-side MIME validated)
        │         │
        │         ├──► VERIFIED  (registrar confirms → parent email sent)
        │         │
        │         └──► REJECTED  (registrar rejects with reason → parent email sent)
        │                   │
        │                   └──► SUBMITTED again (parent re-uploads)
        │
        └──► TO FOLLOW  (registrar marks — physical copy to follow)
```

## 10. PAYMENT SCHEME FLOW

```
[Parent logs into portal after enrollment confirmed]
        │
        ▼
Payment Scheme Page (Step 2)
  · Must select scheme before SOA is accessible
  · Options: Annual (₱95,890) | Semi-Annual (₱55,855 down) |
             Quarterly (₱35,010 down) | Monthly (₱23,430 down)
        │
        ▼
Scheme Confirmed (locked — cannot change)
  · Installment schedule generated in payment_schedules table
  · Downpayment due: June 30 of current school year
  · Monthly due dates: last day of each month (Jul–Feb)
  · ₱500 penalty auto-applied to overdue installments
        │
        ▼
SOA Page (Step 3 — now accessible)
  · View fee breakdown and installment schedule
  · Upload proof of payment
```

## 11. PARENT PORTAL TIMELINE

| Step | Condition |
|---|---|
| ✅ Applied | `enrollments` row exists for student + active school year |
| ✅ Documents | All required `student_requirements` rows have `status = 'verified'` |
| ✅ Payment | At least 1 `payments` row with `amount_paid > 0` |
| ✅ Enrolled | `enrollments.status = 'enrolled'` |


---

## 12. DATABASE TABLES (21 Tables)

| Table | Purpose |
|---|---|
| `users` | Admin staff accounts (superadmin, registrar, finance) with brute-force lockout columns |
| `parent_accounts` | Parent/guardian portal accounts with brute-force lockout columns |
| `parent_student_links` | M:N junction — one parent → many students |
| `students` | Core student records (personal info, address, education history, SPED flag) |
| `grade_levels` | Reference: Nursery, Kinder 1-2, Grade 1-10 |
| `sections` | Sections per grade level with capacity |
| `school_years` | School year management (one active at a time) |
| `enrollments` | One enrollment record per student per school year; stores payment_plan |
| `enrollment_timeline` | Step-by-step enrollment progress log |
| `requirements` | Master list of required documents (new / old / both student types) |
| `student_requirements` | Per-student document submission, status, reject reason, uploaded_by |
| `fees` | Fee breakdown per grade per school year (8 fee types) |
| `payments` | Payment transactions per student per fee with proof_file |
| `payment_schedules` | Installment schedule rows generated when payment scheme is selected |
| `discounts` | Scholarship and discount records per student (percentage or fixed amount) |
| `notifications` | In-system alerts for admin staff |
| `parent_notifications` | In-system alerts for parents |
| `audit_log` | Admin action history (who did what, when, on which record) |
| `clearance` | Per-department clearance status per student |
| `promotions` | School year promotion log |
| `messages` | Internal messaging between staff accounts |

---

## 13. SECURITY MEASURES IMPLEMENTED

| Area | Measure |
|---|---|
| **SQL Injection** | All user-supplied input uses parameterized prepared statements (`bind_param`) throughout |
| **XSS** | All output uses `htmlspecialchars()` including DB-derived values in HTML class attributes |
| **File Uploads** | Server-side MIME type detection via `finfo_file()` — browser-supplied type is ignored |
| **Upload Directory** | `.htaccess` in `pages/uploads/` blocks PHP/script execution and directory listing |
| **File Extensions** | Extension derived from verified MIME type, not original filename |
| **Brute Force** | 5 failed login attempts locks account for 15 minutes (both admin and parent portals) |
| **Session Security** | `session_regenerate_id(true)` called on every successful login |
| **Role-Based Access** | `requireRole()` enforced server-side on every sensitive page and POST handler |
| **Payment Reset** | Server-side role check (superadmin/registrar only) — not just hidden in HTML |
| **Portal Ownership** | `auth.php` re-validates that `$_SESSION['student_id']` belongs to the logged-in parent on every request |
| **Input Validation** | Fee amounts > 0, discount percentage 0–100, date format validated, role whitelist enforced |
| **Password Policy** | Admin: min 8 chars; Parent: min 8 chars + 1 number + 1 special character |
| **DB Error Handling** | Connection errors logged to `error_log`, generic 503 shown to user (no credential leakage) |
| **Path Traversal** | `doc_download.php` uses `basename()` and rejects filenames with `..` |

---

## 14. SYSTEM ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────┐
│              COJ ENROLLMENT & RECORDS MANAGEMENT SYSTEM         │
│                                                                 │
│  ┌──────────────┐   ┌──────────────────┐   ┌────────────────┐  │
│  │  PUBLIC SITE │   │   ADMIN PANEL    │   │ PARENT PORTAL  │  │
│  │  home.php    │   │  pages/          │   │ portal/        │  │
│  │              │   │                  │   │                │  │
│  │ Enrollment   │   │ Dashboard        │   │ Dashboard      │  │
│  │ Form         │   │ Students         │   │ Requirements   │  │
│  │              │   │ Enrollment       │   │ Payment Scheme │  │
│  │ No login     │   │ Requirements     │   │ SOA            │  │
│  │ required     │   │ Payments / SOA   │   │ Notifications  │  │
│  │              │   │ Fees             │   │                │  │
│  │              │   │ Discounts        │   │ Login required │  │
│  │              │   │ Reports          │   │ (parent acct)  │  │
│  │              │   │ Notifications    │   │                │  │
│  │              │   │ Users (SA only)  │   │                │  │
│  │              │   │ School Years(SA) │   │                │  │
│  │              │   │ Backup (SA only) │   │                │  │
│  └──────┬───────┘   └────────┬─────────┘   └───────┬────────┘  │
│         │                    │                      │           │
│         └────────────────────┼──────────────────────┘           │
│                              ▼                                  │
│                    ┌─────────────────┐                          │
│                    │   MySQL DB      │                          │
│                    │ school_registrar│                          │
│                    │   21 tables     │                          │
│                    └────────┬────────┘                          │
│                             │                                   │
│                             ▼                                   │
│                    ┌─────────────────┐                          │
│                    │  PHPMailer      │                          │
│                    │  Gmail SMTP     │                          │
│                    │  (email notifs) │                          │
│                    └─────────────────┘                          │
└─────────────────────────────────────────────────────────────────┘

Tech Stack:
  Backend:    PHP 8+
  Database:   MySQL / MariaDB (via XAMPP or Railway)
  Frontend:   HTML, CSS, Vanilla JS, Bootstrap Icons
  Email:      PHPMailer 7.x via Gmail SMTP (App Password)
  Address:    Philippine PSGC JSON (Region → Province → City → Barangay)
  Export:     SheetJS (xlsx) for Excel export
  Security:   finfo MIME detection, parameterized queries, .htaccess upload guard
```

---

## 15. KEY DESIGN DECISIONS

| Decision | Rationale |
|---|---|
| Separate `parent_accounts` from `users` | Parents and staff are fundamentally different user types with different auth flows and data |
| `parent_student_links` junction table | One parent can have multiple children; one child can have multiple guardians |
| Temp LRN (`P-XXXXXXXXXX`) for online enrollments | LRN is assigned by DepEd; system generates a placeholder until official LRN is provided by registrar |
| `UNIQUE(student_id, school_year_id)` on enrollments | Prevents duplicate enrollment records for the same student in the same year |
| `UNIQUE(student_id, requirement_id, school_year_id)` on student_requirements | Prevents duplicate document records; uses ON DUPLICATE KEY UPDATE for re-uploads |
| Proportional payment distribution | Total payment distributed across fees proportionally for accurate per-fee balance tracking |
| Proof of payment lock | Parent cannot re-upload receipt while one is pending verification — prevents spam uploads |
| Payment scheme locked after selection | Prevents parents from switching plans after installments are generated |
| Downpayment due June 30 | Aligns with school year start; gives parents time to pay before July classes begin |
| End-of-month due dates | More intuitive than 1st-of-month; uses `date('t', mktime())` for leap-year-safe calculation |
| `notify_parent()` sends email automatically | Every portal notification also fires an email — no separate email call needed at each call site |
| `finfo_file()` for MIME detection | Browser-supplied `$_FILES['type']` is trivially spoofable; server-side detection is required |
| `.htaccess` in uploads directory | Prevents uploaded PHP webshells from being executed even if extension check is bypassed |
| Brute-force lockout in DB | Persists across sessions and server restarts; 15-minute window balances security and usability |
| `session_regenerate_id(true)` on login | Prevents session fixation attacks |
| Audit log | Every sensitive action (payment reset, document verify/reject, enrollment status change) logged with user name and timestamp |
