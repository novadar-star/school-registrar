# COJ Catholic Progressive School
## Student Enrollment and Records Management System

A web-based enrollment and records management system built for COJ Catholic Progressive School. Designed to replace manual, paper-based processes with a secure, organized, and accessible digital system serving both the school administration and the parent community.

---

## System Overview

| | |
|---|---|
| **School** | COJ Catholic Progressive School |
| **Scope** | Junior High School — Grades 7 to 10 | (subject to change once presentation is done)
| **Interfaces** | Admin Portal + Parent Portal + Public Landing Page |
| **Deployment** | LAN / Intranet (XAMPP) |
| **Version** | 2.0 |

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Backend | PHP 8.x |
| Database | MySQL / MariaDB |
| Server | Apache via XAMPP |
| Icons | Bootstrap Icons 1.11 |
| Charts | Chart.js 4.4 |
| Export | SheetJS (xlsx) |
| Tunnel (dev) | ngrok |

---

## Features

### Public Landing Page (`home.php`)
- School overview, mission & vision
- Online enrollment form — no LRN required (assigned by registrar)
- Auto-generates enrollment reference number (e.g. `ENR-2025-0001`)
- Parent Portal and Admin Login links
- Responsive footer with quick links

### Admin Portal (`index.php` → `pages/`)

#### Dashboard
- Stat cards: Total Students, Enrolled, Pending, Total Collection
- Bar chart — students per grade level
- Section enrollment table
- Recent registrations
- Payment summary (Paid / Partial / Unpaid)

#### Student Records
- Add, edit, archive students with photo upload
- Search by name or LRN
- Filter by grade, status, school year
- Pagination (10 per page)
- Export to CSV (respects active filters)
- Student profile with parent account creation

#### Enrollment Management
- Enroll students with section assignment
- Status tracking: Pending → Enrolled → Dropped
- Reference number per enrollment
- Search by name, LRN, or reference number
- Filter tabs by status
- Auto-creates clearance record on approval

#### Requirements Tracker
- Define required documents per student type (new/returning)
- View per-student document status: Missing / Submitted / Verified
- Verify or reject uploaded documents
- Default requirements: PSA, Form 138, Good Moral, 2x2 Photo, Baptismal

#### Payments
- Record payments per student per fee
- Payment methods: Cash, Check, Bank Transfer
- Upload proof of payment
- OR number tracking
- View Statement of Account (SOA) per student
- Printable SOA

#### Fee Structure
- Define fees per grade level per school year
- Add, edit, delete fee types
- Auto-computes balance on payment

#### Student Clearance
- 3-department sign-off: Library, Registrar, Finance
- Role-based: Finance clears Finance; Registrar clears Library + Registrar
- Undo sign-off support
- Auto-created when student is enrolled

#### Reports
- Enrollment by grade (new/old/enrolled/pending/dropped)
- Payment summary by grade (paid/partial/unpaid/collection total)
- Filter by school year
- Export both reports to Excel (.xlsx)

#### Notes
- Personal notes per user (not shared)
- Categories: General, Academic, Meeting, Concern
- Search and filter
- Auto-save (3 seconds), Ctrl+S shortcut
- Unsaved changes warning

#### Archived Records
- View archived students
- Restore or permanently delete (superadmin only)

#### User Management (Superadmin only)
- Add, edit, activate/deactivate accounts
- Roles: Superadmin, Registrar, Finance

#### School Year Management (Superadmin only)
- Add new school years
- Set active school year

### Parent Portal (`portal/`)

#### Login
- Separate session from admin portal
- Linked to a specific student record

#### Dashboard
- Student info card with enrollment status
- Summary: documents verified, total paid, clearance status

#### Requirements Upload
- View required documents with status
- Upload files (JPG, PNG, PDF, max 5MB)
- Real-time status: Missing / Under Review / Verified

#### Statement of Account
- View all fees, amounts, payments, balances
- Upload proof of payment per fee
- View existing proof files

#### Clearance Status
- Real-time view of Library, Registrar, Finance sign-offs
- Shows cleared date per department

---

## Database Schema

```
users               — admin accounts (superadmin, registrar, finance)
parent_accounts     — parent/guardian portal accounts
school_years        — enrollment periods (e.g. SY 2025-2026)
grade_levels        — Grade 7 to Grade 10
sections            — Newton, Einstein, Curie, Franklin (per grade)
students            — student records
enrollments         — enrollment status per student per SY + ref number
fees                — fee types per grade per SY
payments            — payment records with method, proof, OR number
requirements        — required document types
student_requirements — per-student document submissions and status
clearance           — 3-dept clearance sign-offs per student per SY
notes               — personal notes per admin user
```

---

## Setup Instructions

### Requirements
- XAMPP (Apache + MySQL)
- PHP 8.0+
- Browser (Chrome recommended)

### Installation

**1.** Copy project to:
```
C:\xampp\htdocs\school-registrar\
```

**2.** Start Apache and MySQL in XAMPP Control Panel.

**3.** Open phpMyAdmin → create database `school_registrar`.

**4.** Run the SQL in `mysql/initialization.php` in the phpMyAdmin SQL tab.

**5.** Open browser:
```
http://localhost/school-registrar/home.php        ← Landing page
http://localhost/school-registrar/                ← Admin login
http://localhost/school-registrar/portal/login.php ← Parent portal
```

**6.** Default superadmin:
```
Email:    superadmin@school.com
Password: Admin@1234
```
> Change this immediately after first login.

---

## Roles

| Role | Access |
|---|---|
| Superadmin | Full access + user management + school year management |
| Registrar | Student records, enrollment, requirements, clearance (library + registrar) |
| Finance | Payments, SOA, fees, clearance (finance only) |

---

## Folder Structure

```
school-registrar/
├── home.php                    # Public landing page
├── index.php                   # Admin login
├── logout.php
├── css/
│   ├── styles.css              # Global design system
│   ├── home.css                # Landing page
│   ├── portal.css              # Parent portal
│   ├── login.css
│   ├── dashboard.css
│   ├── students.css
│   ├── enrollment.css
│   ├── payments.css
│   ├── fees.css
│   ├── requirements.css
│   ├── clearance.css
│   ├── reports.css
│   ├── notes.css
│   ├── profile.css
│   ├── add.css
│   ├── users.css
│   └── archived.css
├── js/
│   ├── nav.js
│   ├── notes.js
│   ├── students.js
│   └── add.js
├── pages/
│   ├── dashboard.php
│   ├── students.php
│   ├── enrollment.php
│   ├── payments.php
│   ├── fees.php
│   ├── requirements.php
│   ├── clearance.php
│   ├── soa.php
│   ├── reports.php
│   ├── notes.php
│   ├── users.php
│   ├── school_years.php
│   ├── archived.php
│   ├── student_profile.php
│   ├── add.php / edit.php / delete.php
│   ├── export.php
│   ├── create_parent.php
│   └── uploads/
├── portal/
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── requirements.php
│   ├── soa.php
│   ├── clearance.php
│   └── includes/
│       ├── auth.php
│       └── nav.php
├── mysql/
│   ├── db.php
│   └── initialization.php
└── images/
    ├── COJ.png
    └── login-bg.png
```

---

## Security

- Prepared statements throughout — no SQL injection
- Passwords hashed with bcrypt (`password_hash`)
- Session-based auth on every page
- Role checks on restricted pages
- File uploads restricted to image/PDF MIME types
- LAN/intranet deployment — data stays on-premises
- Separate session namespaces for admin and parent portals

---

## Known Limitations / Future Work

- No student attendance tracking (teacher-encoded)
- No grade/marks recording or report card generation
- No Form 137 / SF10 printing
- No email notifications
- Single-school deployment only
- Parent portal requires manual account creation by registrar

---
