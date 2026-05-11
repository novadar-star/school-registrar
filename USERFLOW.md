# COJ Catholic Progressive School
## Enrollment & Records Management System
### Presentation Guide — Group 5, Adamson University

---

## SLIDE 1 — INTRODUCTION / BACKGROUND OF THE STUDY

COJ Catholic Progressive School is a private Catholic institution offering Preschool, Elementary, and Junior High School education. Prior to this system, the school managed student enrollment and records entirely through:

- **Google Forms** for enrollment applications
- **Manual spreadsheets** for student records and payment tracking
- **Physical folders** for document requirements
- **No centralized database** — data was scattered across multiple files and staff members

This created recurring problems: duplicate records, lost documents, no real-time payment visibility, and no way for parents to track their child's enrollment status without calling the school.

---

## SLIDE 2 — STATEMENT OF THE PROBLEM

| # | Problem | Impact |
|---|---|---|
| 1 | Enrollment done via Google Forms with no database backend | Data is unstructured, hard to query, and prone to loss |
| 2 | No centralized student records system | Staff manually search spreadsheets; errors are common |
| 3 | Payment tracking done manually per student | Finance staff cannot see real-time balances or collection totals |
| 4 | Document requirements tracked on paper | No visibility on which students have missing documents |
| 5 | Parents have no way to check enrollment status | Constant phone calls to the registrar's office |
| 6 | No role-based access | All staff share the same files with no accountability |
| 7 | No data backup mechanism | Risk of permanent data loss from hardware failure |

---

## SLIDE 3 — OBJECTIVES OF THE STUDY

### General Objective
To design and develop a web-based Enrollment and Records Management System for COJ Catholic Progressive School that centralizes student data, streamlines enrollment processing, and provides a self-service portal for parents.

### Specific Objectives
1. Implement a normalized relational database (MySQL) to store and manage student, enrollment, payment, and document records
2. Develop a CRUD interface for student records management with search, filter, and pagination
3. Build an online enrollment form that replaces the current Google Forms workflow and feeds directly into the database
4. Create a payment tracking module that records fees, payments, and balances per student
5. Implement a document requirements tracker with upload, verify, and reject functionality
6. Provide a parent-facing portal where guardians can monitor enrollment status, submit documents, and view their Statement of Account
7. Enforce role-based access control (Superadmin, Registrar, Finance) to ensure data accountability
8. Enable database backup and CSV/Excel export for data portability and disaster recovery

---

## SLIDE 4 — SCOPE AND LIMITATIONS

### Scope (What the system covers)

| Module | Description |
|---|---|
| **Student Records** | Full CRUD — add, edit, view, search, filter, archive students |
| **Online Enrollment Form** | Public form at `home.php` replacing Google Forms; creates student + parent account in DB |
| **Enrollment Management** | Admin processes applications: Pending → Enrolled / Dropped |
| **Fee Structure** | Define fees per grade level (Tuition, Miscellaneous, PTA, Development, Books) |
| **Payment Tracking** | Record payments, track balances, view SOA per student |
| **Document Requirements** | Track submission status per student; verify, reject, upload on behalf |
| **Parent Portal** | Parents view enrollment status, upload documents, view SOA, upload proof of payment |
| **Reports & Export** | Enrollment and payment summaries exportable to Excel/CSV |
| **User Management** | Superadmin creates and manages staff accounts with role-based access |
| **Database Backup** | Full database export via admin panel |
| **In-System Notifications** | Bell notifications for admin staff on new enrollments, document submissions, payment uploads |

### Limitations (What the system does NOT cover)

| Limitation | Reason |
|---|---|
| **No email notifications** | SMTP configuration requires external mail server infrastructure; out of scope for local deployment |
| **No school year switching / promotion** | Multi-year data migration and student promotion logic is a planned future enhancement |
| **No grading or attendance** | Academic records management is outside the scope of this project |
| **No mobile application** | System is web-based and desktop-optimized only |
| **No payment gateway** | Proof of payment is uploaded manually; online payment processing is not integrated |
| **No SMS notifications** | Requires third-party SMS API; out of scope |
| **Single school deployment** | Not designed for multi-branch or multi-tenant use |
| **Parent accounts created via enrollment form only** | Registrar cannot manually create parent accounts |

---

## SLIDE 5 — HOW THE SYSTEM SOLVES THE CLIENT'S PROBLEMS

| Client Problem | System Solution |
|---|---|
| Google Forms with no database | Online enrollment form writes directly to MySQL — structured, queryable, permanent |
| No centralized student records | Students module with full CRUD, search by name/LRN, filter by grade/type/year |
| Manual payment tracking | Payments module with per-student fee breakdown, balance tracking, and collection totals |
| Document tracking on paper | Requirements tracker with per-student status (Missing / Submitted / Verified / Rejected) |
| Parents calling to check status | Parent portal with real-time enrollment timeline, document status, and SOA |
| No role-based access | Three roles: Superadmin (full), Registrar (enrollment/docs), Finance (payments/fees) |
| Risk of data loss | Database backup module exports full SQL dump; reports export to Excel |

---

## SLIDE 6 — SYSTEM FLOWCHART

### A. PARENT / ENROLLMENT FLOW

```
[Parent] visits home.php (public, no login required)
        │
        ▼
Fills out Online Enrollment Form
  ├── Student Information (name, grade, birthday, sex, religion)
  ├── Education History (last school attended)
  ├── Parent/Guardian Information (name, contact, email)
  ├── Home Address (Region → Province → City → Barangay)
  ├── Creates portal password
  └── Attaches documents (Form 138, Good Moral Certificate)
        │
        ▼
Submits Form
        │
        ├──► [DB] Creates parent_accounts record (if new email)
        ├──► [DB] Creates students record (temp LRN assigned)
        ├──► [DB] Creates parent_student_links record
        ├──► [DB] Creates enrollments record (status: PENDING)
        ├──► [DB] Inserts notifications for all active admin users
        └──► Shows confirmation screen with reference number
        │
        ▼
Parent logs into portal/login.php
        │
        ▼
Parent Dashboard
  · Enrollment progress timeline: Applied → Documents → Payment → Enrolled
  · Summary: documents verified, total paid, balance
        │
        ├──► Requirements tab
        │     · View required documents and their status
        │     · Upload documents (JPG, PNG, PDF, max 3MB)
        │     · Re-upload if rejected (with reject reason shown)
        │
        └──► Statement of Account tab
              · View fee breakdown per grade level
              · View payment history
              · Upload proof of payment (GCash, bank transfer, cash receipt)
              · Cannot re-upload while receipt is pending verification
```

---

### B. ADMIN / REGISTRAR FLOW

```
[Registrar] logs into index.php
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
  · Search by name, LRN, or reference number
        │
        ├── Select ENROLLED → fees auto-assigned to student
        │                   → admin notification sent
        │
        └── Select DROPPED  → admin notification sent
        │
        ▼
Students Page
  · Full list of all students (paginated, 10 per page)
  · Search by name or LRN
  · Filter by grade, student type (new/old), school year
  · Add new student manually (CRUD)
  · Edit student details
  · View student profile
        │
        ▼
Requirements Tracker
  · Lists all students with document completion summary
  · Click student → view per-document status
        │
        ├── VERIFY document → parent portal notification sent
        ├── REJECT document → parent portal notification sent (with reason)
        ├── UPLOAD on behalf of student (admin upload)
        ├── MARK RECEIVED (physical document received, auto-verified)
        └── MARK TO FOLLOW (document to be submitted later)
        │
        ▼
Reports Page
  · Enrollment summary by grade (new vs old, enrolled vs pending)
  · Payment summary by grade (total paid, balance, fully paid count)
  · Export to Excel (.xlsx)
```

---

### C. FINANCE FLOW

```
[Finance] logs into index.php
        │
        ▼
Fees Page
  · Define fee structure per grade level
  · Fee types: Tuition, Miscellaneous, PTA Fund, Development, Books, SPED, Other
  · Add, edit, delete fees
        │
        ▼
Discounts Page
  · Apply discounts per student: Employee, Sibling, Scholarship, Reservation, Other
  · Percentage-based or fixed amount
  · Automatically deducted from student SOA
        │
        ▼
Payments Page
  · Lists all students with payment status (Paid / Partial / Unpaid)
  · View parent-uploaded proof of payment
  · Record payment (amount, OR number, payment method, date)
  · Reset payment if needed (with audit log entry)
        │
        ▼
SOA Page (admin view)
  · Full fee breakdown per student
  · Payment history with OR numbers
```

---

### D. SUPERADMIN FLOW

```
[Superadmin] has access to ALL of the above, PLUS:

Users Page
  · Create staff accounts (Registrar, Finance, Superadmin)
  · Edit name, email, role, password
  · Activate / Deactivate accounts
  · Unlock locked accounts (after failed login attempts)

School Years Page
  · Add new school year (format: YYYY-YYYY)
  · Set active school year (all data scopes to this year)

Backup Page
  · Export full database as SQL dump
  · Download for offline storage / disaster recovery
```

---

## SLIDE 7 — ENTITY RELATIONSHIP DIAGRAM (ERD)

### Core Entities and Relationships

```
┌─────────────────┐         ┌──────────────────────────────────────┐
│  school_years   │         │               users                  │
│─────────────────│         │──────────────────────────────────────│
│ PK id           │         │ PK id                                │
│    label        │         │    name, email (UNIQUE)              │
│    is_active    │         │    password (hashed)                 │
└────────┬────────┘         │    role: superadmin/registrar/finance│
         │                  │    is_active, failed_attempts        │
         │                  └──────────────┬───────────────────────┘
         │                                 │ 1:N
         │                                 ▼
         │                  ┌──────────────────────────────────────┐
         │                  │           notifications              │
         │                  │ PK id, FK user_id                    │
         │                  │ type, title, body, link, is_read     │
         │                  └──────────────────────────────────────┘
         │
         │    ┌─────────────────────────────────────────────────────────┐
         │    │                       students                          │
         │    │─────────────────────────────────────────────────────────│
         │    │ PK id                                                   │
         │    │    lrn (UNIQUE), first_name, middle_name, last_name     │
         │    │ FK grade_level_id → grade_levels.id                    │
         │    │ FK section_id     → sections.id                        │
         │    │ FK school_year_id → school_years.id  ◄─────────────────┤
         │    │    student_type: new / old                              │
         │    │    birthday, sex, religion                              │
         │    │    province, city_municipality, barangay                │
         │    │    last_school, school_address                          │
         │    │    is_sped, sped_notes, photo, is_archived              │
         │    └──────────────────────┬──────────────────────────────────┘
         │                           │
         │         ┌─────────────────┼──────────────────────┐
         │         │                 │                      │
         │         ▼                 ▼                      ▼
         │  ┌─────────────┐  ┌──────────────┐  ┌──────────────────────┐
         │  │ enrollments │  │   payments   │  │ student_requirements │
         │  │─────────────│  │──────────────│  │──────────────────────│
         │  │ PK id       │  │ PK id        │  │ PK id                │
         │  │ ref_number  │  │ FK student_id│  │ FK student_id        │
         │  │ FK student_id  │ FK fee_id    │  │ FK requirement_id    │
         │  │ FK sy_id    │  │ amount_paid  │  │ FK school_year_id    │
         │  │ FK grade_id │  │ balance      │  │ file_path            │
         │  │ FK section_id  │ status       │  │ status               │
         │  │ status      │  │ or_number    │  │ (missing/submitted/  │
         │  │ (pending/   │  │ payment_method  │  verified/rejected/  │
         │  │  enrolled/  │  │ proof_file   │  │  to_follow)          │
         │  │  dropped)   │  │ paid_at      │  │ reject_reason        │
         │  │ payment_plan│  └──────┬───────┘  │ verified_by          │
         │  └─────────────┘         │          └──────────┬───────────┘
         │                          ▼                     │
         │                   ┌──────────────┐             ▼
         │                   │     fees     │  ┌──────────────────────┐
         │                   │──────────────│  │    requirements      │
         │                   │ PK id        │  │──────────────────────│
         │                   │ FK grade_id  │  │ PK id                │
         │                   │ FK sy_id     │  │ name, description    │
         │                   │ name         │  │ student_type         │
         │                   │ fee_type     │  │ (new / old / both)   │
         │                   │ amount       │  │ is_required          │
         │                   └──────────────┘  └──────────────────────┘
         │
         │    ┌─────────────────────────────────────────────────────────┐
         │    │                   parent_accounts                       │
         │    │─────────────────────────────────────────────────────────│
         │    │ PK id                                                   │
         │    │    name, email (UNIQUE), password (hashed)              │
         │    │    contact, province, city_municipality, barangay       │
         │    │    birthday, sex, civil_status, religion                │
         │    │    is_active                                            │
         │    └──────────────────────┬──────────────────────────────────┘
         │                           │
         │              ┌────────────┴────────────┐
         │              │                         │
         │              ▼                         ▼
         │  ┌───────────────────────┐  ┌──────────────────────────┐
         │  │ parent_student_links  │  │   parent_notifications   │
         │  │───────────────────────│  │──────────────────────────│
         │  │ PK id                 │  │ PK id                    │
         │  │ FK parent_id          │  │ FK parent_id             │
         │  │ FK student_id         │  │ FK student_id            │
         │  │ UNIQUE(parent,student)│  │ type, title, body        │
         │  └───────────────────────┘  │ is_read                  │
         │                             └──────────────────────────┘
         │
         └──► discounts
              PK id
              FK student_id → students.id
              FK school_year_id → school_years.id
              type: employee/sibling/scholarship/reservation/other
              percentage, fixed_amount, label, notes


SUPPORT TABLES
──────────────────────────────────────────────────────────────────
grade_levels   │ PK id, name (Nursery, Kinder 1-2, Grade 1-10)
sections       │ PK id, FK grade_level_id, name, capacity
audit_log      │ PK id, user_id, user_name, action, target, details
clearance      │ PK id, FK student_id, FK school_year_id,
               │ library_status, registrar_status, finance_status
```

### Cardinality Summary

| Relationship | Type | Constraint |
|---|---|---|
| `school_years` → `students` | 1 : N | One year, many students |
| `grade_levels` → `students` | 1 : N | One grade, many students |
| `students` → `enrollments` | 1 : 1 per year | UNIQUE (student_id, school_year_id) |
| `parent_accounts` ↔ `students` | M : N | Via `parent_student_links` junction table |
| `students` → `student_requirements` | 1 : N | UNIQUE (student_id, requirement_id, school_year_id) |
| `fees` → `payments` | 1 : N | One fee type → many payment records |
| `students` → `payments` | 1 : N | One student → many payment records |
| `students` → `discounts` | 1 : N | One student → multiple discounts |
| `users` → `notifications` | 1 : N | ON DELETE CASCADE |
| `parent_accounts` → `parent_notifications` | 1 : N | ON DELETE CASCADE |

---

## SLIDE 8 — DATABASE TABLES (19 Tables)

| Table | Purpose |
|---|---|
| `users` | Admin staff accounts (superadmin, registrar, finance) |
| `parent_accounts` | Parent/guardian portal accounts |
| `parent_student_links` | M:N junction — one parent → many students |
| `students` | Core student records |
| `grade_levels` | Reference: Nursery, Kinder 1-2, Grade 1-10 |
| `sections` | Sections per grade level |
| `school_years` | School year management (one active at a time) |
| `enrollments` | One enrollment record per student per school year |
| `enrollment_timeline` | Step-by-step enrollment progress log |
| `requirements` | Master list of required documents |
| `student_requirements` | Per-student document submission and status |
| `fees` | Fee breakdown per grade per school year |
| `payments` | Payment transactions per student per fee |
| `discounts` | Scholarship and discount records per student |
| `notifications` | In-system alerts for admin staff |
| `parent_notifications` | In-system alerts for parents |
| `audit_log` | Admin action history (who did what, when) |
| `clearance` | Per-department clearance status per student |
| `promotions` | School year promotion log |

---

## SLIDE 9 — PROPOSED SYSTEM OVERVIEW

### System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    COJ ENROLLMENT SYSTEM                        │
│                                                                 │
│  ┌──────────────┐   ┌──────────────────┐   ┌────────────────┐  │
│  │  PUBLIC SITE │   │   ADMIN PANEL    │   │ PARENT PORTAL  │  │
│  │  home.php    │   │  pages/          │   │ portal/        │  │
│  │              │   │                  │   │                │  │
│  │ Enrollment   │   │ Dashboard        │   │ Dashboard      │  │
│  │ Form         │   │ Students         │   │ Requirements   │  │
│  │              │   │ Enrollment       │   │ SOA            │  │
│  │ No login     │   │ Requirements     │   │ Notifications  │  │
│  │ required     │   │ Payments         │   │                │  │
│  │              │   │ Fees             │   │ Login required │  │
│  │              │   │ Discounts        │   │ (parent acct)  │  │
│  │              │   │ Reports          │   │                │  │
│  │              │   │ Notifications    │   │                │  │
│  │              │   │ Users (SA only)  │   │                │  │
│  │              │   │ School Years(SA) │   │                │  │
│  │              │   │ Backup (SA only) │   │                │  │
│  └──────┬───────┘   └────────┬─────────┘   └───────┬────────┘  │
│         │                    │                      │           │
│         └────────────────────┼──────────────────────┘           │
│                              │                                  │
│                              ▼                                  │
│                    ┌─────────────────┐                          │
│                    │   MySQL DB      │                          │
│                    │ school_registrar│                          │
│                    │  19 tables      │                          │
│                    └─────────────────┘                          │
└─────────────────────────────────────────────────────────────────┘

Tech Stack:
  Backend:   PHP 8+
  Database:  MySQL / MariaDB (via XAMPP)
  Frontend:  HTML, CSS, Vanilla JS, Bootstrap Icons
  Address:   Philippine PSGC JSON (Region → Province → City → Barangay)
  Export:    SheetJS (xlsx) for Excel export
```

### Role Access Matrix

| Feature | Superadmin | Registrar | Finance | Parent |
|---|---|---|---|---|
| Dashboard | ✅ | ✅ | ✅ | ✅ (portal) |
| Students (CRUD) | ✅ | ✅ | ❌ | ❌ |
| Enrollment | ✅ | ✅ | ❌ | ✅ (submit only) |
| Requirements | ✅ | ✅ | ❌ | ✅ (upload only) |
| Payments | ✅ | ✅ | ✅ | ✅ (view + upload proof) |
| Fees | ✅ | ✅ | ✅ | ❌ |
| Discounts | ✅ | ✅ | ✅ | ❌ |
| Reports | ✅ | ✅ | ✅ | ❌ |
| Users | ✅ | ❌ | ❌ | ❌ |
| School Years | ✅ | ❌ | ❌ | ❌ |
| Backup | ✅ | ❌ | ❌ | ❌ |

---

## SLIDE 10 — ENROLLMENT STATUS & DOCUMENT STATUS FLOWS

### Enrollment Status

```
[Parent submits form]
        │
        ▼
    PENDING
  (awaiting registrar review)
        │
        ├──► ENROLLED  (registrar approves → fees auto-assigned)
        │
        └──► DROPPED   (rejected or withdrew)
```

### Document Status

```
    MISSING
  (default — not yet submitted)
        │
        ├──► SUBMITTED  (parent uploads file)
        │         │
        │         ├──► VERIFIED  (registrar confirms)
        │         │
        │         └──► REJECTED  (registrar rejects with reason)
        │                   │
        │                   └──► SUBMITTED again (parent re-uploads)
        │
        └──► TO FOLLOW  (registrar marks — physical copy to follow)
```

### Parent Portal Timeline

| Step | Condition in DB |
|---|---|
| ✅ Applied | `enrollments` row exists for student + active school year |
| ✅ Documents | At least 1 `student_requirements` row with `status = 'verified'` |
| ✅ Payment | At least 1 `payments` row with `status IN ('paid', 'partial')` |
| ✅ Enrolled | `enrollments.status = 'enrolled'` |

---

## SLIDE 11 — SAMPLE TRANSACTIONS (DEMO SCRIPT)

Use this sequence for the live demonstration:

### Transaction 1 — New Enrollment (Parent Side)
1. Open `home.php` in browser (no login)
2. Fill out enrollment form with sample student data
3. Submit → show confirmation screen with reference number
4. Log into admin panel → show new notification in bell
5. Go to Enrollment page → show new PENDING application

### Transaction 2 — Process Enrollment (Registrar Side)
1. On Enrollment page, change status to ENROLLED
2. Go to Payments page → show fees auto-assigned to student
3. Go to Requirements tracker → show student with all docs MISSING

### Transaction 3 — Document Verification
1. Log into parent portal with the account created during enrollment
2. Go to Requirements → upload a sample document
3. Switch to admin panel → Requirements tracker → VERIFY the document
4. Switch back to parent portal → show status changed to VERIFIED

### Transaction 4 — Payment Recording
1. Parent portal → SOA → upload proof of payment
2. Admin panel → Payments → view uploaded receipt → Record Payment (enter OR number)
3. Parent portal → SOA → show balance updated

### Transaction 5 — Reports & Backup
1. Go to Reports page → show enrollment and payment summary tables
2. Click Export → download Excel file
3. Go to Backup page → export database

---

## APPENDIX — KEY DESIGN DECISIONS

| Decision | Rationale |
|---|---|
| Separate `parent_accounts` from `users` | Parents and staff are fundamentally different user types with different auth flows and data |
| `parent_student_links` junction table | One parent can have multiple children enrolled; one child can have multiple guardians |
| Temp LRN (`P-XXXXXXXXXX`) for online enrollments | LRN is assigned by DepEd; system generates a placeholder until official LRN is provided |
| `UNIQUE(student_id, school_year_id)` on enrollments | Prevents duplicate enrollment records for the same student in the same year |
| `UNIQUE(student_id, requirement_id, school_year_id)` on student_requirements | Prevents duplicate document records; uses ON DUPLICATE KEY UPDATE for re-uploads |
| Proportional payment distribution | Total payment is distributed across fees proportionally for accurate per-fee balance tracking |
| Proof of payment lock | Parent cannot re-upload receipt while one is pending verification — prevents spam uploads |
| Audit log | Every sensitive action (payment reset, document verify/reject) is logged with user name and timestamp |
| No email notifications | SMTP requires external infrastructure; declared as a system limitation; in-system notifications cover the same use case within the local deployment |
