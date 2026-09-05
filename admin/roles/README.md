# KHSMIS — Role-Based Feature Folders

Each subfolder maps to one staff role. Every file inside is scoped to that role's permissions.

## Structure

```
admin/roles/
├── _shared.php              # Bootstrap helper (auth + permission check + header)
│
├── sys_admin/               # System Administrator
│   ├── dashboard.php        # System overview, DB stats, audit log
│   ├── users.php            # Manage all users
│   ├── roles.php            # Roles & permissions matrix
│   ├── audit_logs.php       # Security & audit events
│   ├── settings.php         # System configuration
│   ├── schools.php          # School profile
│   └── reports.php          # System-wide reports
│
├── school_admin/            # School Administrator
│   ├── dashboard.php        # School operations overview
│   ├── students.php         # All students
│   ├── teachers.php         # Teaching staff
│   ├── classes.php          # Class management
│   ├── subjects.php         # Subject management
│   ├── academic_years.php   # Academic years & semesters
│   ├── timetable.php        # School timetable
│   ├── applications.php     # Admissions applications
│   ├── finance.php          # Finance overview
│   ├── announcements.php    # School announcements
│   ├── events.php           # School events
│   ├── approvals.php        # Approval center
│   ├── settings.php         # School settings
│   └── reports.php          # All reports
│
├── principal/               # Principal
│   ├── dashboard.php        # Executive overview + approval summary
│   ├── approvals.php        # Approval center (marks, admissions, discipline)
│   ├── students.php         # Student directory
│   ├── applications.php     # Admission applications
│   ├── marks_approval.php   # Approve submitted marks
│   ├── report_cards.php     # Generate & publish report cards
│   ├── promotion.php        # Year-end promotion
│   ├── finance.php          # Financial overview
│   ├── discipline.php       # Discipline cases
│   ├── teachers.php         # Teaching staff management
│   ├── announcements.php    # School announcements
│   └── settings.php         # School settings
│
├── vice_principal/          # Vice Principal
│   ├── dashboard.php        # Academic overview + pending approvals
│   ├── approvals.php        # Approval center
│   ├── marks_approval.php   # Review & approve teacher marks
│   ├── students.php         # Student directory
│   ├── attendance.php       # Attendance monitoring
│   ├── results.php          # Academic results
│   ├── broadsheets.php      # Class broadsheets
│   ├── report_cards.php     # Generate & approve report cards
│   ├── discipline.php       # Discipline cases
│   ├── timetable.php        # Timetable management
│   ├── promotion.php        # Promotion management
│   └── announcements.php    # Announcements
│
├── registrar/               # Registrar
│   ├── dashboard.php        # Admissions funnel metrics
│   ├── applications.php     # All applications
│   ├── admission_decisions.php  # Process admission decisions
│   ├── students.php         # Student records (create/update)
│   ├── guardians.php        # Parent/guardian management
│   ├── documents.php        # Student documents
│   ├── entrance_exams.php   # Entrance examination management
│   ├── promotion.php        # Recommend promotion
│   ├── report_cards.php     # Generate report cards
│   ├── attendance.php       # View attendance
│   └── reports.php          # Student & admission reports
│
├── accountant/              # Accountant / Bursar
│   ├── dashboard.php        # Financial metrics, collections
│   ├── payments.php         # Record & view payments
│   ├── fee_structures.php   # Manage fee structures
│   ├── student_statements.php  # Per-student balance statements
│   └── reports.php          # Financial reports & exports
│
├── finance_officer/         # Finance Officer
│   ├── dashboard.php        # Basic finance view
│   ├── payments.php         # Record payments (no fee management)
│   └── student_statements.php  # View student balances
│
├── teacher/                 # Teacher
│   ├── dashboard.php        # My classes, marks status, today's attendance
│   ├── marks_entry.php      # Enter marks (own classes only)
│   ├── attendance.php       # Take attendance (own classes only)
│   ├── students.php         # My students (own classes only)
│   ├── results.php          # View class results
│   ├── timetable.php        # My timetable
│   └── report_cards.php     # View published report cards
│
├── class_teacher/           # Class Teacher
│   ├── dashboard.php        # Class overview + marks workflow status
│   ├── marks_entry.php      # Enter & submit marks
│   ├── attendance.php       # Daily attendance register
│   ├── students.php         # Class student list
│   ├── discipline.php       # Record discipline incidents
│   ├── results.php          # Class results
│   ├── report_cards.php     # Add teacher comments
│   ├── timetable.php        # Class timetable
│   └── announcements.php    # Create class announcements
│
├── discipline_officer/      # Discipline Officer
│   ├── dashboard.php        # Open cases, suspensions overview
│   ├── incidents.php        # Manage discipline records
│   ├── students.php         # View students
│   └── announcements.php    # View announcements
│
├── librarian/               # Librarian
│   ├── dashboard.php        # Books, issued, overdue metrics
│   ├── books.php            # Manage book catalogue
│   ├── borrowing.php        # Issue & return books
│   ├── students.php         # Find students/borrowers
│   └── reports.php          # Library reports
│
└── ict_officer/             # ICT Officer
    ├── dashboard.php        # System support overview
    ├── users.php            # View/create/reset users
    ├── audit_logs.php       # System & security logs
    └── settings.php         # System settings
```

## How it works

Every feature file in a role folder sets three variables then requires `_shared.php`:

```php
<?php
$pageTitle    = 'Feature Name';
$activeAdmin  = 'sidebar_key';
$requiredPerm = 'module.action';   // e.g. 'marks.create', 'finance.view'
require_once dirname(__DIR__).'/_shared.php';
require_once dirname(__DIR__,2).'/actual_feature.php';
```

`_shared.php` handles:
1. DB config bootstrap
2. `requireAuth()` — must be logged in
3. `requireStaff()` — must be a staff role
4. `requirePermission($requiredPerm)` — must have the specific permission
5. `admin_header.php` — renders the correct role-scoped sidebar

## Permission model

Permissions follow `module.action` naming:
- `marks.create`, `marks.submit`, `marks.approve`, `marks.return`, `marks.publish`
- `students.view`, `students.create`, `students.update`, `students.delete`
- `finance.view`, `finance.create_payment`, `finance.manage_fees`
- `admissions.view`, `admissions.approve`, `admissions.reject`
- etc. (110 permissions total)

The sidebar **automatically shows only items the logged-in user has permission for** — no hard-coded role checks in HTML.
