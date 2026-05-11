# COJ Catholic Progressive School — Enrollment System
## User Flow & Database Reference

---

## PART 1 — GENERAL SYSTEM FLOWCHART

### Overview: How the System Works

```
┌─────────────────────────────────────────────────────────────────┐
│                    COJ ENROLLMENT SYSTEM                        │
│                                                                 │
│   PUBLIC          PARENT PORTAL        ADMIN (Registrar)        │
│   home.php        portal/             pages/                    │
└─────────────────────────────────────────────────────────────────┘
```

---

### ENROLLMENT JOURNEY (Parent Side)

```
Parent visits home.php
        │
        ▼
Fills out Online Enrollment Form
  · Student information
  · Parent/Guardian information
  · Home address
  · Creates portal password
  · Attaches documents (Form 138, Good Moral)
        │
        ▼
Submits the form
        │
        ├──► System creates parent account
        ├──► System creates student record
        ├──► System links parent to student
        ├──► Enrollment application created (status: PENDING)
        ├──► Admin staff notified
        └──► Parent receives confirmation email
             (with reference number + portal login)
        │
        ▼
Parent logs into portal/login.php
        │
        ▼
Parent Dashboard
  · Sees enrollment progress timeline
  · Applied ──► Documents ──► Payment ──► Enrolled
        │
        ├──► Goes to Requirements tab
        │     Uploads required documents
        │
        └──► Goes to Statement of Account tab
              Views fee breakdown
              Uploads proof of payment
```

---

### ENROLLMENT JOURNEY (Registrar Side)

```
Registrar logs into index.php
        │
        ▼
Dashboard
  · Sees pending applications count
  · Sees total enrolled, collection summary
        │
        ▼
Enrollment Page
  · Reviews pending applications
        │
        ├── APPROVE
        │     · Status → Enrolled
        │     · Parent receives approval email
        │     · Portal timeline updates
        │
        └── REJECT
              · Status → Dropped
              · Parent notified
        │
        ▼
Requirements Tracker
  · Views documents submitted by parents
        │
        ├── VERIFY document → parent notified ✓
        ├── REJECT document → parent notified, must re-upload
        ├── UPLOAD on behalf of student
        └── MARK TO FOLLOW → parent reminded
        │
        ▼
Payments Page
  · Records payment (amount, OR number, method)
  · Confirms parent-uploaded proof of payment
  · Parent receives payment confirmation email
        │
        ▼
Reports Page
  · Enrollment summary per grade level
  · Payment collection summary
  · Export to CSV
```

---

### FINANCE SETUP (Registrar/Superadmin)

```
Fees Page
  · Set tuition, miscellaneous, PTA, development, books per grade
  · Fees automatically appear in parent SOA

Discounts Page
  · Apply scholarship, sibling, employee discounts per student
  · Deducted automatically from student total

School Years Page (Superadmin only)
  · Create new school year
  · Set active year — all data scopes to this year
```

---

### SYSTEM ROLES

```
SUPERADMIN ──► Full access + Users + School Years management
REGISTRAR  ──► Enrollment + Students + Requirements + Payments + Fees + Discounts + Reports
PARENT     ──► Portal only — Dashboard + Requirements + SOA
```

---

## PART 2 — DATABASE TABLES & RELATIONSHIPS



## PART 3 — TABLE RELATIONSHIP MAP

```
school_years ◄──────────────────────────────────────────────────────┐
     │                                                               │
     │ 1:N                                                           │
     ▼                                                               │
enrollments ──────────────► students ◄──── parent_student_links ────► parent_accounts
     │                          │                                         │
     │ 1:N                      │ 1:N                                     │ 1:N
     ▼                          │                                         ▼
enrollment_timeline             ├──► student_requirements ◄── requirements
                                │         (file uploads,
                                │          verify/reject)
                                │
                                ├──► payments ◄── fees ◄── grade_levels
                                │         (amount paid,
                                │          proof of payment)
                                │
                                └──► discounts
                                          (scholarships,
                                           employee discounts)

users ──► notifications
users ──► audit_log

parent_accounts ──► parent_notifications
```

---

## PART 4 — KEY FOREIGN KEY RULES

| Relationship | Type | Rule |
|---|---|---|
| `students` → `grade_levels` | Many-to-One | Every student must have a grade level |
| `students` → `school_years` | Many-to-One | Student is scoped to a school year |
| `enrollments` → `students` | Many-to-One | One enrollment per student per year (UNIQUE constraint) |
| `parent_student_links` → `parent_accounts` + `students` | Many-to-Many | One parent → many children; UNIQUE on (parent_id, student_id) |
| `student_requirements` → `students` + `requirements` + `school_years` | Many-to-Many | UNIQUE on (student_id, requirement_id, school_year_id) |
| `payments` → `students` + `fees` | Many-to-One | Payment tied to a specific fee for a specific student |
| `fees` → `grade_levels` + `school_years` | Many-to-One | Fee structure is per grade per year |
| `discounts` → `students` + `school_years` | Many-to-One | Discount applied per student per year |
| `notifications` → `users` | Many-to-One | ON DELETE CASCADE — deleting a user removes their notifications |
| `parent_notifications` → `parent_accounts` | Many-to-One | ON DELETE CASCADE |

---

## PART 5 — ENROLLMENT STATUS FLOW

```
PENDING ──► REGISTERED ──► ENROLLED
   │                           │
   └──────────────────────────► DROPPED
```

| Status | Meaning |
|---|---|
| `pending` | Application submitted, awaiting registrar review |
| `registered` | Registrar acknowledged, documents being processed |
| `enrolled` | Fully approved and enrolled |
| `dropped` | Rejected or withdrew |

---

## PART 6 — DOCUMENT STATUS FLOW

```
MISSING ──► SUBMITTED ──► VERIFIED
   ▲              │
   │              └──► REJECTED ──► (parent re-uploads) ──► SUBMITTED
   │
   └──── TO FOLLOW (flagged for later submission)
```

---

## PART 7 — PORTAL TIMELINE LOGIC

The 4-step progress bar in the parent portal lights up based on these DB conditions:

| Step | Lights up when... | DB Check |
|---|---|---|
| Applied | Enrollment form submitted | `enrollments` row exists for student + active school year |
| Documents | At least one document verified | `student_requirements` has 1+ row with `status='verified'` |
| Payment | At least one payment recorded | `payments` has 1+ row with `status IN ('paid','partial')` |
| Enrolled | Registrar approved | `enrollments.status = 'enrolled'` |


---

## PART 8 — ENTITY RELATIONSHIP DIAGRAM (ERD)

```
┌──────────────────┐         ┌──────────────────────────────────────┐
│   school_years   │         │              users                   │
│──────────────────│         │──────────────────────────────────────│
│ PK id            │         │ PK id                                │
│    label         │         │    name                              │
│    is_active     │         │    email          (UNIQUE)           │
└────────┬─────────┘         │    password                          │
         │                   │    role  (superadmin / registrar)    │
         │                   │    is_active                         │
         │                   └──────────────┬───────────────────────┘
         │                                  │ 1:N
         │                                  ▼
         │                   ┌──────────────────────────────────────┐
         │                   │          notifications               │
         │                   │──────────────────────────────────────│
         │                   │ PK id                                │
         │                   │ FK user_id  → users.id               │
         │                   │    type, title, body, is_read        │
         │                   └──────────────────────────────────────┘
         │
         │    ┌──────────────────────────────────────────────────────────┐
         │    │                      students                            │
         │    │──────────────────────────────────────────────────────────│
         │    │ PK id                                                    │
         │    │    lrn              VARCHAR(20) — temp: P-XXXXXXXXXX     │
         │    │    first_name, middle_name, last_name                    │
         │    │ FK grade_level_id  → grade_levels.id                    │
         │    │ FK section_id      → sections.id                        │
         │    │ FK school_year_id  → school_years.id  ◄─────────────────┤
         │    │    student_type    (new / old)                           │
         │    │    birthday, sex, religion                               │
         │    │    province, city_municipality, barangay                 │
         │    │    last_school, school_year_graduated, school_address    │
         │    │    is_sped, sped_notes                                   │
         │    │    is_archived, photo                                    │
         │    └──────────────────────┬───────────────────────────────────┘
         │                           │
         │         ┌─────────────────┼──────────────────────┐
         │         │                 │                      │
         │         ▼                 ▼                      ▼
         │  ┌─────────────┐  ┌──────────────┐   ┌──────────────────────┐
         │  │ enrollments │  │   payments   │   │ student_requirements │
         │  │─────────────│  │──────────────│   │──────────────────────│
         │  │ PK id       │  │ PK id        │   │ PK id                │
         │  │ ref_number  │  │ FK student_id│   │ FK student_id        │
         │  │ FK student_id  │ FK fee_id    │   │ FK requirement_id    │
         │  │ FK sy_id    │  │    amount_paid    │ FK school_year_id   │
         │  │ FK grade_id │  │    balance   │   │    file_path         │
         │  │ FK section_id  │    status    │   │    status            │
         │  │    status   │  │    or_number │   │  (missing/submitted/ │
         │  │  (pending/  │  │    pay_method│   │   verified/rejected/ │
         │  │  registered/│  │    pay_plan  │   │   to_follow)         │
         │  │  enrolled/  │  │    surcharge │   │    reject_reason     │
         │  │  dropped)   │  │    proof_file│   │    verified_by       │
         │  │  pay_plan   │  └──────┬───────┘   └──────────┬───────────┘
         │  └──────┬──────┘         │                      │
         │         │                ▼                      ▼
         │         ▼         ┌──────────────┐   ┌──────────────────────┐
         │  ┌──────────────┐ │     fees     │   │    requirements      │
         │  │ enrollment_  │ │──────────────│   │──────────────────────│
         │  │ timeline     │ │ PK id        │   │ PK id                │
         │  │──────────────│ │ FK grade_id  │   │    name              │
         │  │ PK id        │ │ FK sy_id     │   │    description       │
         │  │ FK enroll_id │ │    name      │   │    student_type      │
         │  │    step      │ │    fee_type  │   │  (new/old/both)      │
         │  │    status    │ │    amount    │   │    is_required       │
         │  │    done_by   │ └──────────────┘   │    sort_order        │
         │  │    done_at   │                    └──────────────────────┘
         │  └──────────────┘
         │
         │    ┌──────────────────────────────────────────────────────────┐
         │    │                  parent_accounts                         │
         │    │──────────────────────────────────────────────────────────│
         │    │ PK id                                                    │
         │    │    name, email (UNIQUE), password                        │
         │    │    contact                                               │
         │    │    province, city_municipality, barangay                 │
         │    │    birthday, sex, civil_status, religion                 │
         │    │    is_active                                             │
         │    └──────────────────────┬───────────────────────────────────┘
         │                           │
         │              ┌────────────┴────────────┐
         │              │                         │
         │              ▼                         ▼
         │  ┌───────────────────────┐  ┌──────────────────────────┐
         │  │ parent_student_links  │  │   parent_notifications   │
         │  │───────────────────────│  │──────────────────────────│
         │  │ PK id                 │  │ PK id                    │
         │  │ FK parent_id          │  │ FK parent_id             │
         │  │    → parent_accounts  │  │    → parent_accounts     │
         │  │ FK student_id         │  │ FK student_id            │
         │  │    → students         │  │    → students            │
         │  │ UNIQUE(parent,student)│  │    type, title, body     │
         │  └───────────────────────┘  │    is_read               │
         │                             └──────────────────────────┘
         │
         └──► discounts
              ────────────────────────────
              PK id
              FK student_id  → students.id
              FK school_year_id → school_years.id
                 type  (employee/sibling/scholarship/reservation/other)
                 percentage, fixed_amount, label, notes


┌──────────────────────────────────────────────────────────────────┐
│                     SUPPORT TABLES                               │
├──────────────────┬───────────────────────────────────────────────┤
│ grade_levels     │ PK id, name (Nursery → Grade 10)              │
│ sections         │ PK id, FK grade_level_id, name, capacity      │
│ audit_log        │ PK id, user_id, user_name, action, target,    │
│                  │ target_id, details, created_at                │
│ clearance        │ PK id, FK student_id, FK school_year_id,      │
│                  │ library_status, registrar_status,             │
│                  │ finance_status                                │
│ promotions       │ PK id, FK from_sy_id, FK to_sy_id,            │
│                  │ promoted_by, students_count                   │
└──────────────────┴───────────────────────────────────────────────┘
```

---

### CARDINALITY SUMMARY

| Relationship | Cardinality | Notes |
|---|---|---|
| `school_years` → `students` | 1 : N | One school year has many students |
| `grade_levels` → `students` | 1 : N | One grade has many students |
| `students` → `enrollments` | 1 : 1 per year | UNIQUE on (student_id, school_year_id) |
| `enrollments` → `enrollment_timeline` | 1 : N | One enrollment has multiple timeline steps |
| `parent_accounts` ↔ `students` | M : N | Via `parent_student_links` junction table |
| `students` → `student_requirements` | 1 : N | One student has many document records |
| `requirements` → `student_requirements` | 1 : N | One requirement type → many student submissions |
| `fees` → `payments` | 1 : N | One fee type → many payment records |
| `students` → `payments` | 1 : N | One student → many payment records |
| `students` → `discounts` | 1 : N | One student can have multiple discounts |
| `users` → `notifications` | 1 : N | One user → many notifications |
| `parent_accounts` → `parent_notifications` | 1 : N | One parent → many notifications |
