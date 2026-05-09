# COJ Catholic Progressive School — Enrollment System

A web-based enrollment and records management system built for **COJ Catholic Progressive School** (Junior High School, Grades 7–10). Developed as a capstone project by Group 5, Adamson University.

---

## Tech Stack

- **Backend:** PHP 8+, MySQL (MariaDB)
- **Frontend:** HTML, CSS, Vanilla JS, Bootstrap Icons
- **Local Deployment:** XAMPP
- **Address Data:** Philippine PSGC JSON (Region → Province → City → Barangay)

---

## System Users

| User | Access |
|---|---|
| Superadmin | Full access — all modules + user/school year management |
| Registrar | Enrollment, students, requirements |
| Finance | Payments, fees, discounts |
| Parent | Portal — enrollment status, documents, SOA |

---

## Admin Side Features

### Dashboard
- Total enrolled, pending, and dropped students
- Charts: students per grade level, section distribution
- Recent enrollment applications and payment summaries

### Enrollment Management
- View all enrollment applications submitted via the public form
- Approve, reject, or update enrollment status (pending → registered → enrolled → dropped)
- Assign section and payment plan per student
- Reference number auto-generated per application (e.g. `ENR-2026-0001`)

### Students
- View-only list of all enrolled students
- Filter by grade level, student type (new/returning), and school year
- Search by name or LRN
- View individual student profile (personal info, academic, documents, parent account)

### Requirements Tracker
- Define required documents per student type (new, returning, or both)
- View submission status per student: Missing → Submitted → Verified / Rejected
- Admin can upload documents on behalf of students
- Verify or reject submitted documents with optional reject reason

### Payments
- Record payments per student per fee
- Track amount paid, balance, OR number, payment method, and proof of payment
- Confirm parent-uploaded payment receipts
- Payment plan support: Annual, Semi-Annual, Quarterly, Monthly

### Fees
- Set fee breakdown per grade level per school year
- Fee types: Tuition, Miscellaneous, PTA Fund, Development Fee, Books, SPED, Reservation
- Fees auto-compute total per student based on grade level

### Discounts
- Apply discounts per student: Employee, Sibling, Scholarship, Reservation, Other
- Supports percentage-based or fixed amount discounts

### Reports
- Enrollment summary: new vs returning, enrolled vs pending
- Payment summary: total collected, paid, unpaid balances
- Exportable to CSV

### Notifications
- In-system alerts for admin staff
- Triggered on new enrollment applications, document submissions, and payment uploads

### Users (Superadmin only)
- Create and manage admin accounts (superadmin, registrar, finance)
- Account lockout support after failed login attempts

### School Years (Superadmin only)
- Manage school years, set active year
- All enrollment, fee, and payment data is scoped per school year

---

## Parent Portal Features

### Dashboard
- Welcome screen with active school year
- Multi-child switcher (one parent account can have multiple enrolled children)
- Visual enrollment progress timeline: Applied → Documents → Payment → Enrolled
- Quick summary: documents verified count, total paid, outstanding balance
- Fee breakdown preview with payment plan options

### Requirements
- View all required documents and their status
- Upload documents (JPG, PNG, PDF — max 3MB)
- Re-upload rejected documents
- See reject reason if a document was declined

### Statement of Account (SOA)
- Full fee breakdown per grade level
- Payment history with OR numbers and dates
- Upload proof of payment (GCash receipt, bank transfer screenshot)
- Balance tracking

---

## Public Enrollment Form

Accessible at `home.php` — no login required.

- Multi-section form: Student Info, Education History, Parent/Guardian Info, Address, Additional Children, Attachments
- Philippine address cascade: Region → Province → City/Municipality → Barangay (PSGC data)
- Supports enrolling multiple children in one submission
- Auto-creates parent portal account with password set by parent during submission
- Generates reference number per child (e.g. `ENR-2026-0001`)
- Notifies all admin staff on submission

---

## Database

19 tables with normalized schema and proper foreign key relationships.

| Table | Purpose |
|---|---|
| `users` | Admin staff accounts |
| `parent_accounts` | Parent portal accounts |
| `parent_student_links` | Many-to-many: one parent → many students |
| `students` | Core student records |
| `grade_levels` | Grade 7–10 reference |
| `sections` | Sections per grade level |
| `school_years` | School year management |
| `enrollments` | One enrollment record per student per school year |
| `enrollment_timeline` | Step-by-step enrollment progress log |
| `requirements` | Master list of required documents |
| `student_requirements` | Per-student document submission and status |
| `fees` | Fee breakdown per grade per school year |
| `payments` | Payment transactions per student |
| `discounts` | Scholarship and discount records |
| `notifications` | Admin in-system alerts |
| `parent_notifications` | Parent in-system alerts |
| `audit_log` | Admin action history |
| `clearance` | Per-department clearance status |
| `promotions` | School year promotion log |

---

## Setup (XAMPP)

1. Clone or copy project to `C:\xampp\htdocs\school-registrar\`
2. Start Apache and MySQL in XAMPP Control Panel
3. Open `http://localhost/school-registrar/setup.php` to initialize the database
4. Log in at `http://localhost/school-registrar/index.php`

**Default admin credentials:**
- Email: `superadmin@school.com`
- Password: `Admin@1234`

> Change the default password after first login.

---

## Limitations

- No email notifications in current deployment (SMTP not configured)
- No attendance, grading, or report card system — out of scope
- Parent accounts created through enrollment form only
- Single school deployment only

---

## Developed By

Group 5 — Adamson University, Information Management
- Sumanting, Darla Nova
- Singh, Gurjindier
- Garra, Aaron James
- Lastrilla, Timothy James
- Moloboco, Juan Gabriel
- Raduban, James Adrian
