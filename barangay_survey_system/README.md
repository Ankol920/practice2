# Barangay Health Center Survey Management System

A web-based survey management system built for **IT 305 – Advance Web Development**
(Student Group Activity, Act 5 – Set B), using **HTML, CSS, JavaScript, PHP, and MySQL**.

## Features

**Resident Module**
- Login using Resident Number (default password = Resident Number) at the site's home page
- Forced password change on first login
- View and answer active surveys (one submission per survey, enforced by the database)
- Confirmation page after submission
- Update personal profile (via modal form) — includes a read-only Residency Status
  (Permanent / Temporary) set by barangay staff
- A small AJAX-loaded chart on the dashboard shows survey completion progress

**Staff/Admin Module**
- Secure login at a hidden, unlisted URL: `/admin` (not linked anywhere in the public UI —
  you access it by typing the address directly into the browser)
- **Role-based permissions:** the `staff` role can manage surveys, questions, residents, and
  reports, but cannot create/edit/delete staff accounts. Only the `admin` role can manage
  Staff Accounts (the nav link is hidden entirely for non-admin staff, and the page itself
  is protected server-side).
- Create, edit, and activate/deactivate surveys
- Add questions of type: Multiple Choice, Yes/No, Rating Scale (1–5), Short Answer
- Set survey opening/closing dates
- View respondents and monitor participation, including each resident's Residency Status
  (Permanent / Temporary), editable inline via a dropdown
- Automatic computation of survey results with bar-chart style visual breakdowns
- Dashboard statistics rendered as interactive charts (bar, doughnut, pie), loaded via AJAX
  from a JSON endpoint and rendered with Chart.js
- Printable reports and CSV export (opens directly in Excel)
- Manage resident accounts (add residents via a modal form, reset passwords, set residency status)
- Manage staff accounts (Admin only), via a modal form

**Interface**
- Only one public login page (Resident Login) — no more "choose your portal" landing screen
- Forms such as "Add Resident", "Add Staff Account", "Add Question", and "Update Profile"
  open in modal dialogs, with a matching success/confirmation modal after submission
- All destructive actions (delete, deactivate, reset password) use a styled confirmation
  modal instead of the browser's plain `confirm()` popup

## Requirements
- PHP 7.4+ (tested against PHP 8.x syntax)
- MySQL / MariaDB
- A local server stack such as **XAMPP**, **WAMP**, or **Laragon**

## Setup Instructions

1. Copy the `barangay_survey_system` folder into your server's web root:
   - XAMPP: `C:\xampp\htdocs\barangay_survey_system`
   - WAMP: `C:\wamp64\www\barangay_survey_system`

2. Start Apache and MySQL from your control panel.

3. Import the database:
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin`)
   - Click **Import**, choose `database/barangay_survey.sql`, and run it.
   - This creates the `barangay_survey_system` database with all tables and sample data.

4. Check `config/db.php` and update `DB_USER` / `DB_PASS` if your MySQL setup requires
   credentials other than the XAMPP default (`root` / no password).

5. Open the system in your browser:
   ```
   http://localhost/barangay_survey_system/
   ```

## Default Login Credentials

| Role     | Username / Resident No. | Password   |
|----------|--------------------------|------------|
| Staff    | `admin`                  | `admin123` |
| Resident | `2026-0001`              | `2026-0001`|
| Resident | `2026-0002`              | `2026-0002`|

Residents are prompted to change their password on first login.

**Staff/Admin sign-in:** go to `http://localhost/barangay_survey_system/admin/` (type it directly
into the browser's address bar — there is no visible link to it from the resident-facing pages).

## Folder Structure

```
barangay_survey_system/
├── config/
│   ├── db.php              -- database connection
│   └── functions.php       -- shared helper functions (auth guards, escaping, etc.)
├── assets/
│   ├── css/style.css
│   └── js/script.js
├── includes/
│   ├── header_resident.php
│   ├── header_staff.php
│   └── footer.php
├── admin/                    -- Hidden staff/admin login entry point
│   └── index.php
├── resident/                -- Resident Module pages
│   ├── login.php             (stub redirect -> ../index.php)
│   ├── logout.php
│   ├── dashboard.php
│   ├── ajax_stats.php         -- JSON endpoint for the dashboard chart
│   ├── surveys.php
│   ├── survey_form.php
│   ├── submit.php
│   ├── confirmation.php
│   ├── profile.php
│   └── change_password.php
├── staff/                    -- Staff/Admin Module pages
│   ├── login.php              (stub redirect -> ../admin/index.php)
│   ├── logout.php
│   ├── dashboard.php
│   ├── ajax_stats.php         -- JSON endpoint for the dashboard charts
│   ├── survey_management.php
│   ├── add_survey.php
│   ├── edit_survey.php
│   ├── question_management.php
│   ├── resident_management.php
│   ├── results_dashboard.php
│   ├── reports.php
│   ├── export_csv.php
│   └── user_management.php    -- Admin only
├── database/
│   └── barangay_survey.sql
├── index.php                  -- The one public login page (Resident Login)
└── README.md
```

## Database Design

- `residents` — resident accounts and profile info
- `staff` — staff/admin accounts
- `surveys` — survey metadata, opening/closing dates, status
- `survey_questions` — questions per survey, with a `question_type` enum
- `survey_choices` — choice options for multiple-choice questions
- `responses` — one row per resident submission (UNIQUE constraint on
  `survey_id` + `resident_id` prevents duplicate submissions at the database level)
- `survey_results` — one row per answered question (choice, rating, or free text)
- `login_history` — optional login audit log

## Notes for Your Submission

Remember the activity's submission requirements:
1. Complete source code (this project).
2. Video presentation showing how to use the system.
3. Documentation of which group member developed which part.
4. This `.sql` file (already included in `database/`).
5. Screenshots: Login Page, Dashboard/Home Page, Update Personal Information Page,
   Successful Update Confirmation.

---

## Update — Staff onboarding and resident deletion control

### Staff accounts are now created in two steps

An administrator creates the **employment record** only: name, position, date
hired, and role. The system issues the Staff Number, derives a username, and
sets the Staff Number as a temporary password. There is no password box on the
Add Staff form, so no administrator ever knows a staff member's working
password.

The new hire supplies the rest themselves. Their first login is locked to two
steps before anything else opens up:

1. **Set Password** (`staff/change_password.php`) — replace the temporary one.
2. **Complete Your Profile** (`staff/complete_profile.php`) — contact number,
   email, birthdate, sex, home address, emergency contact.

The gate lives inside `require_staff_login()` in `config/functions.php`, so
every staff page is covered and a page added later cannot forget to apply it.

### Record numbers and usernames

| | Format | Changes? |
|---|---|---|
| Staff Number | `S-2026-0001` | Never — it is the permanent record key |
| Username | `jbartolome` (first initial + surname) | Yes, an admin can correct it |

Usernames are 4–30 characters, lowercase letters, digits, periods, and
underscores, starting with a letter. They are checked for collisions against
both staff usernames and Resident Numbers, because both are typed into the same
login box. The `R-YYYY-NNNN` and `S-YYYY-NNNN` shapes are reserved.

### Deleting a resident is now an administrator action

Deleting a resident cascades to their survey responses, so the permissions were
inverted before this change: staff could delete but could not deactivate.

| Action | Staff | Admin |
|---|---|---|
| Deactivate / reactivate a resident | Yes | Yes |
| Reset a resident password | Yes | Yes |
| Delete a resident | Request only | Yes |
| Create / deactivate staff accounts | No | Yes |

Staff raise a request with a reason from the Residents page. An administrator
approves or rejects it in **Deletion Requests** (`staff/deletion_requests.php`).
A rejection requires a note. The request row keeps a snapshot of the resident's
number and name, so the audit trail outlives the record it authorised the
deletion of.

### Schema

`config/db.php` applies everything automatically on the first page load.
`database/staff_onboarding_and_deletion_requests.sql` is the explicit record if
you prefer to run it in phpMyAdmin.

Note that `is_first_login` defaults to `0` and `profile_completed` to `1`. Adding
a column applies its default to every existing row, so the opposite defaults
would lock the current admin out of their own system on the next page load. New
accounts get the pending values written explicitly by the INSERT.

### Revision — staff form simplified, roster restricted, profile expanded

**Add Staff Account** no longer asks for Position, and no longer offers a Role
selector. Every account created there is a Staff account; `role` is set in PHP,
so posting `role=admin` directly to the endpoint does nothing.

**The hand-over slip shows two lines, not four.** The Staff Number and the
temporary password were always the same string printed twice. They are now one
row, labelled for what each value is for:

| Username | what they type to log in |
| Staff Number | their one-time password, and the permanent record key |

**Staff Accounts is now withheld from the staff portal entirely.** Staff opening
User Management see Resident Accounts and nothing else — not a read-only roster.
The query that loads the roster does not even run for them.

**My Profile** gained a photo upload (`uploads/staff/`, validated by reading the
image header rather than trusting the extension, 5 MB cap, previous file
replaced on change) and a structured **Permanent Address**:

Household Number · Purok/Street · Highway/Subdivision/Village · Barangay ·
Municipality/City · Postal Code · Province

Required: Household Number, Barangay, Municipality/City, Province. The parts are
also joined into `staff.address` on save, so anything that already reads that
column keeps working.

### Revision — Staff Number as username, gated address changes

**The username field is gone from Add Staff.** The Staff Number is now the
username, the record key, and the one-time password — one issued identifier per
person, nothing for the administrator to invent. A new hire types
`S-2026-0002` as both username and password on their first login, exactly as a
resident uses their Resident Number, then sets a real password immediately. The
username stays the Staff Number permanently.

**Staff address changes are reviewed.** The permanent address is on file for
employment records, so it is not self-service after onboarding:

| When | What happens |
|---|---|
| During onboarding (first entry) | Writes straight through — a new hire cannot be made to wait for approval to finish setup |
| Any later change | Goes to `staff_update_requests` and waits for an administrator |
| Contact number, email, birthdate, sex, emergency contact | Always save immediately — these are the staff member's own |

While a request is open the address fields are locked and the pending change is
shown, so a second conflicting request cannot be raised. Approving copies the
parts onto the staff row and rebuilds the single-line address from them;
rejecting requires a note. Review both resident and staff requests under
**Address Requests**.

**My Profile** now puts the photo in its own card beside the employment record,
using the same crop-and-zoom widget as the resident profile (shared markup and
IDs, driven by the existing code in `assets/js/script.js`).

**The administrator's own My Profile** is the employment record and photo only.
An administrator has no onboarding step and no address on file to maintain, so
Your Details, Permanent Address, and Emergency Contact are not rendered for them
— and the address inputs are absent from the page, not merely hidden.

### Revision — address parts, courtesy titles, gender options, temporary residence

**Fixed: "No name or address changes were detected to request."**

The address was stored only as a comma-joined string. Every form split it back
apart by position (`explode(', ', $address)[1]`) and the request screen compared
one *reassembled* string against another. Any address whose comma count did not
match the four fields on the form landed in the wrong boxes, and a real change
could rebuild to a string that looked unchanged.

The seven parts are now real columns on `residents`, and change detection
compares them field by field. `address` is still written as the derived display
line so exports and existing screens keep working. Existing four-part addresses
are backfilled automatically; anything with a different shape is left for a
human rather than guessed at, because a wrong guess would put a barangay in the
province box and look correct until someone read it.

**Address format**, used identically for residents and staff:

Household Number · Purok · Street/Highway/Subdivision/Village · Barangay ·
Municipality/City · Postal Code · Province

Required: Household Number, Barangay, Municipality/City, Province. Purok,
Street/Highway, and Postal Code are optional — not every barangay has a purok or
a named street.

**Temporary residence.** Choosing Temporary reveals a residence type
(AR Apartment · DR Dormitory · BH Boarding House · BS Bedspace ·
RL Relative's House · TR Transient Room) and a second, full address. Both
addresses are stored. The block's inputs are `disabled` while hidden, so the
browser cannot enforce `required` on a field nobody can see, and a permanent
resident posts no temporary data. Switching back to Permanent clears any
residence type already on the record.

**Courtesy titles** (Mr., Mrs., Ms., Mx., or Others with a free-text box) on both
the resident and staff forms, displayed before the name. Choosing Others stores
what was typed, not the word "Others"; editing a resident whose title is not in
the list restores it into the free-text box rather than losing it.

**Gender** is now Man / Woman / Non-binary / Prefer not to say for residents and
staff. Note the column widening in the migration: `residents.gender` was an
`ENUM('male','female')` and `staff.gender` a `varchar(10)`. An ENUM silently
truncates anything outside its list, and `prefer_not_to_say` is 17 characters, so
the value migration must run *after* the `MODIFY`.

### Revision — header photo, record headings, split emergency contact, resident identity

**Profile photo now appears in the account chip** for admin and staff. The name
and photo are read from the `staff` table on each page load rather than from the
session, which is written at login — that is why a photo uploaded mid-session
never reached the corner before. The resident header already did this; it now
also applies the courtesy title.

**"Employment record" is now "Admin Record" or "Staff Record"**, depending on the
role viewing it.

**Emergency contact is two fields** — name and number — validated separately. The
old single-line `emergency_contact` column is kept and written in step, and any
existing value is carried into the name half.

**Resident Profile Overview** now lists Courtesy Title, Full Name, First Name,
Middle Name, Surname, and Extension Name. All of them are editable through the
existing Request Change flow, which now carries the title and name parts.

**Birthdate is correctable** through a Request Change button on Personal Details.
It goes through review rather than saving directly, because birthdate sets the
displayed age and senior-citizen eligibility. A birthdate-only request carries no
address parts, so approval deliberately skips the address write — otherwise
approving a birthdate correction would blank the resident's stored address.

### Revision — address breakdown on the resident profile, residence type badge

**Contact Information** keeps the complete address line under the contact
number, and adds an **Address Breakdown** below it listing all seven parts by
name. Seeing the parts named is what makes an address-change request
actionable — the resident can tell which single line is wrong.

A **temporary resident** also sees their Temporary Address, complete line plus
the same seven parts, headed with the residence type. They have two addresses on
file; showing only the permanent one would leave them unable to check where the
health center expects to find them.

**The sidebar** shows the residence type directly under the TEMPORARY RESIDENT
pill — for example `BH · Boarding House`. It renders only for a temporary
resident who has a type on file, so a permanent resident sees nothing extra.
`residence_type_label()` in `config/functions.php` strips the code for places
that only need the words.
