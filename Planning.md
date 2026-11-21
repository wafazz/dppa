# Planning — Dewan Perniagaan Puncak Alam: Member & Committee Management System (v1)

> **Status**: Approved 2026-11-21 and **implemented** (v1 build). See §14 for installed versions, deviations and verification.
> **Source brief**: [docs/001-MasterPrompt.md](docs/001-MasterPrompt.md)
> **Author**: Iris (CS)
> **Date**: 2026-11-21
> **Tier**: **T2 — Full**. Reasons: database schema and migrations, authentication and authorization, personal data of applicants, CSV export, and public form submission (public API surface). All CS gates apply at build time.

---

## 1. Summary

Build the first version of a member and committee management system for the chamber. It has three parts:

1. **Public membership registration** — a mobile-friendly form. A submission creates an application with status **Pending**.
2. **Committee member database** — an authenticated AdminLTE area for searching, reviewing, approving or rejecting applications, and maintaining member records. Approval assigns a unique member ID.
3. **Chamber positions** — assign positions (President, Deputy President, Secretary, Treasurer, Committee Member, Ordinary Member) with term dates, a current flag, and retained history.

Out of scope for v1: advocacy, training, business matching, partnerships, consultations, events, payments, and any other module. The schema and code structure should leave room for them later without building them now.

---

## 2. Current project state (inspected)

| Item | Finding |
|---|---|
| Project contents | Only `docs/001-MasterPrompt.md`. No application code. |
| Git repository | None. |
| `AGENTS.md` / CS project protocols | None inside the project. The CS protocol set lives in `/Users/wafazztechnology/Desktop/CS`. |
| Existing authentication | None. |
| Existing database schema | None. |
| Existing design conventions | None. |

**Implication**: this is a greenfield Laravel project. There is nothing to preserve or integrate with yet, so the plan sets the conventions. The first build step creates the Laravel app in the project root and initializes git.

---

## 3. Implementation approach

- **Architecture**: a Laravel monolith. Blade renders the page layouts (AdminLTE and public pages). Vue.js provides interactive components inside those pages: the member table, filters, modals and status actions. Vite builds the frontend assets.
- **Why not a separate SPA**: the brief asks for AdminLTE as the authenticated layout and for Laravel to own routes, validation and authorization. A Blade shell with Vue islands keeps one deployable app, one auth system and one set of routes.
- **Public vs admin**: the public registration form is a Blade page with minimal Vue, and it has no access to member records. The admin area is behind authentication, and every route is authorized server-side. Hiding a button in the UI is never the access control.
- **Data flow**: all writes go through Laravel form requests (validation), policies (authorization), and services (business rules such as ID assignment, appointment term logic and duplicate checks). Controllers stay thin.
- **Database**: MySQL only, configured through environment variables. No credentials in source.

---

## 4. Technology stack and versions

The brief requires the latest stable versions that work together. Versions below are the **candidates from a web check on 2026-11-21**. Several are not confirmed from an official source, so each one must be re-checked before installation. The build step records the exact installed versions.

| Component | Candidate | Confidence | How to confirm before install |
|---|---|---|---|
| Laravel | **13.12.0** (released 26 May 2026; PHP 8.3+ minimum) | Good — listed on endoflife.date and Laravel release notes | `composer show laravel/framework` after `composer create-project`, and check laravel.com/docs/releases |
| PHP | 8.3+ (use the highest version Laravel 13 supports and the server allows) | To confirm | Check Laravel 13 requirements |
| Vue.js | **3.5.x latest patch** (3.5.45 reported) | Medium — a secondary source. Vue 3.6 was still beta at last check, so **do not use 3.6** | `npm view vue version` and vuejs.org/about/releases |
| AdminLTE | **4.3.1** (CDN changelog, dated 2026-08-10) | Medium | `npm view admin-lte version` and the GitHub release page |
| Bootstrap | **5.3.8** (as cited in the AdminLTE README). Use the version AdminLTE depends on | Medium | Read AdminLTE's `package.json` dependency |
| Select2 | **Latest stable 4.x** | **Low — no stable release confirmed.** The newest tag seen is 4.1.0-rc.0 | `npm view select2 version` and github.com/select2/select2/releases. If no stable 4.1.0 exists, use the latest stable 4.0.x and note it |
| SweetAlert2 | **11.26.x latest** (at least 11.26.17 seen) | Medium — the numbers conflict across sources | `npm view sweetalert2 version` |
| MySQL | **8.4 LTS** (proposed) | To confirm | Check dev.mysql.com and the chosen hosting provider's supported versions |
| Rich-text editor | **Not chosen** — see Decision D2 | — | — |
| Laravel starter for login | **Laravel Breeze** (Blade). Check compatibility with Laravel 13 | To confirm | Breeze release notes |
| Select2 + Vue integration | Wrapper component (see §10 risk R3) | — | — |

**Rule**: if a "latest" version does not work with the others, use the newest version that does, and state the reason in the build report.

---

## 5. Data model (MySQL, Laravel migrations)

| Table | Key columns | Notes |
|---|---|---|
| `users` | id, name, email (unique), password, role, timestamps | Committee accounts only. `role` takes an enum such as `admin`, `committee`, `viewer` (see D3). Initial admin is created by an artisan command, never committed |
| `business_categories` | id, name_bm, name_en, is_active | Source for the category Select2. Seeded; editable by admin (D4) |
| `members` | id, member_no (unique, nullable until approved), applicant_name, business_name, category_id (FK), business_address, phone, email, ssm_no (nullable), business_description (rich text, sanitized), business_needs (rich text, sanitized), status (`pending`/`approved`/`rejected`), applied_at, approved_at (nullable), rejected_at (nullable), consent_given_at, consent_version, timestamps, soft deletes | Member ID assigned only on approval. Indexes on status, category, business_name, email, phone and ssm_no |
| `member_status_logs` | id, member_id (FK), from_status, to_status, changed_by (FK users), note (nullable), created_at | Audit of approve/reject/revert. Needed for the approval date and accountability |
| `chamber_positions` | id, name_bm, name_en, is_active, sort_order, timestamps | Initial six positions seeded. Admin can add or edit names |
| `position_appointments` | id, member_id (FK), chamber_position_id (FK), term_start, term_end (nullable), is_current (boolean), notes (nullable), timestamps | Past appointments retained. `is_current` is maintained by the service layer (see §7.3) |

**Member ID format (proposed, D5)**: `DPPA-YYYY-NNNN`, for example `DPPA-2026-0001`. The sequence is generated inside a database transaction with a row lock, and `member_no` has a unique index. A unique ID is never reused after a rejection.

**Duplicate handling (proposed, D6)**: on submission, warn if the same SSM number, or the same email or phone, already exists in a pending or approved member. Warn on the admin side too. Block exact duplicates on SSM number. Duplicate warnings are shown to the committee for review; the public form only shows a generic "application received" message, so it does not reveal whether a record exists.

---

## 6. Feature plan

### 6.1 Public member registration

- Route: `/register` (Bahasa Malaysia). Mobile-first, single column on small screens.
- Fields exactly as the brief lists: applicant name, business name, category (Select2), business address, phone, email, SSM number (optional), business description (rich text), business needs (rich text), and consent (required checkbox).
- **No** identity card number or other sensitive identifiers.
- Server validation (form request) and inline error messages in Bahasa Malaysia.
- Consent: store `consent_given_at` and the consent text version. A submission without consent is rejected.
- Success page: confirmation message and a note that the application status is **Pending**.
- Rate limiting on the submit route, and a honeypot or CSRF protection against spam.

### 6.2 Authentication and authorization

- Login with Laravel Breeze (Blade). Login page and a logout action.
- No public self-registration for committee users. Accounts are created by an admin, or by a one-time artisan command during setup.
- Roles and policies:
  - `admin`: all actions, including positions and users
  - `committee`: view, search, review, approve and reject members; assign positions
  - `viewer` (optional, D3): read-only
- All admin routes sit behind `auth` middleware plus policies. Public routes never expose member data.
- Session security: secure cookies in production, session regeneration on login, and throttled login attempts.

### 6.3 Member database (admin)

- Member list (DataTables-style server-side pagination, or Vue table with server-side query; see D7):
  - search by name, business name, phone, email, SSM number, member ID
  - sort by any column
  - filter by status, category, and business description/services text
- Member detail page with the record, status history and current positions.
- Edit member details in a **modal**.
- Review queue: pending applications, with **Approve**, **Reject** and **Set to Pending** actions in modals. Each action requires a SweetAlert2 confirmation. Approval assigns the member ID and sets `approved_at`. Rejection sets `rejected_at` and requires a note.
- CSV export of the filtered list (see D8). Columns: member ID, status, names, category, contact, SSM, dates. Contains no rich-text HTML.
- Clear **loading**, **empty** and **error** states on every list, panel and modal.

### 6.4 Chamber positions

- Position list in admin (`Jawatan`), with add and edit in modals. Add/edit position names is limited to `admin` (D3).
- Assign a member to a position in a modal: choose member (Select2), position (Select2), term start, term end (optional), and a current flag.
- Member detail shows **current** positions prominently and a **history** table of past appointments.
- Current positions are visually highlighted (badge) and sortable.

### 6.5 Layout and interface

- AdminLTE 4 layout (sidebar, navbar, content header) for all authenticated pages.
- Every admin **create, edit, review or assignment form opens in a Bootstrap modal**. Modals must work with Vue, Bootstrap, Select2 and keyboard navigation (Esc to close, focus trap, Tab order), and must be usable on mobile.
- All user-facing text in **Bahasa Malaysia**, stored in Laravel language files (`lang/ms/`) so it can be edited later.

---

## 7. Cross-cutting rules (from the brief)

### 7.1 Select2 for every select

Every `<select>` in the system uses Select2: registration category, member filters, status, position assignment, roles, and any future dropdown. Vue and Blade both use the same Select2 wrapper, initialized inside modals with `dropdownParent` set to the modal.

### 7.2 Rich-text for every textarea

- Textareas are replaced by a rich-text editor (D2). Applies to the business description, business needs, status notes, appointment notes, and any other textarea.
- Phone, email and SSM remain normal inputs.
- **Sanitization on write and on render**: strip disallowed tags and attributes with an HTML purifier, on the server, before storage. Render stored HTML only after sanitizing again. Allowed set: basic formatting, lists and links (`rel="noopener noreferrer"`). No scripts, iframes, or inline event handlers.
- Plain-text versions are used in CSV exports and search indexes.

### 7.3 Confirmations

- SweetAlert2 confirmation on every button that **saves, updates, approves, rejects, deletes, or changes status or position**. Cancelling must not send the request and must not change data.
- Navigation, search, modal open/close and pagination do not need confirmation.
- Success and error feedback after every action (toast or alert).
- The confirmation is client-side UX. The server still enforces every rule.

### 7.4 Business logic (services)

- `MemberApprovalService`: approve, reject, set to pending. Within one transaction: write the status log, set dates, and assign a member ID on approval.
- `MemberIdGenerator`: sequence per year, locked row, unique index.
- `PositionAppointmentService`: create or update appointments. Setting `is_current = true` is validated against `term_start`/`term_end` and today's date. Past appointments are never deleted, only ended.
- `DuplicateCheckService`: used by both the public form and admin.

### 7.5 Privacy and data protection

- Store only what administering membership and understanding business categories, services and needs requires.
- No identity card numbers.
- Consent recorded with time and consent text version.
- Member records are soft-deleted, not hard-deleted, so the audit trail stays intact. Permanent deletion is a later decision (D9).
- Applicant data is viewable only by authorized committee users, and CSV export is restricted to `admin` and `committee`.
- Malaysian **Personal Data Protection Act 2010 (PDPA)** applies to collecting and using this data. The consent wording and retention period should be reviewed by the chamber or a legal adviser. This plan does not give legal advice.

---

## 8. Testing and verification plan

| Area | What is tested |
|---|---|
| Migrations and seeds | Run clean on empty MySQL. Seeds create six positions and no real credentials |
| Public registration | Valid submission creates **pending** record. Missing consent, invalid email/phone, and duplicate SSM number are rejected. No sensitive fields accepted |
| Authorization | Guests cannot reach admin routes. `viewer`/`committee`/`admin` permissions match the policy matrix. Public responses never list members |
| Approval flow | Approve assigns a unique ID, sets `approved_at`, writes a status log. Reject writes log and requires a note. Cancelling a SweetAlert2 dialog does not call the endpoint |
| Member ID | Concurrent approvals produce unique IDs (test with parallel requests) |
| Positions | Current/history logic. Ended appointments stay in history. Invalid date ranges are rejected |
| Search, sort, filter | Correct results for each filter and combined filters. Pagination works |
| CSV export | Headers, row count matches filter, no HTML in output |
| Rich text | Script tags and event handlers are stripped on save and on render |
| UI (browser) | Modals open, close with Esc, trap focus, and work at mobile width. Select2 works inside modals. Loading, empty and error states appear |
| Static and security | `coresentinel verify` (Tests, Static Check, Security, Lint, Audit, Diff). Dependency audit for PHP and npm |

Test tooling (proposed): PHPUnit or Pest for feature and unit tests, and a browser test tool (Laravel Dusk or Playwright) for the modal and SweetAlert2 journeys.

---

## 9. Delivery phases

| Phase | Deliverable | Exit check |
|---|---|---|
| **P0 — Confirm** | You approve this plan and the decisions in §11. Versions re-checked | Approval recorded |
| **P1 — Scaffold** | Laravel 13 project in the root, git initialized, MySQL env config (`.env.example` without secrets), Vite, AdminLTE, Bootstrap, Vue, Select2, SweetAlert2 | App boots, connects to MySQL, pages render with AdminLTE |
| **P2 — Auth** | Breeze login, roles, policies, initial admin artisan command | Guest blocked, login works, role checks pass |
| **P3 — Registration** | Public form, validation, consent, duplicate check, Pending status, confirmation page | Public submit creates Pending record |
| **P4 — Member database** | List, search, sort, filter, detail, edit modal, review queue, approve/reject/pending with SweetAlert2, member ID, status log, CSV export | Full review flow works end-to-end |
| **P5 — Positions** | Positions CRUD (admin), appointments in modal, current/history views | Current and history displayed correctly |
| **P6 — Hardening** | Rich-text sanitization, loading/empty/error states, Bahasa Malaysia copy review, mobile pass | Test matrix §8 green |
| **P7 — Verify and document** | `coresentinel verify`, dependency audit, README setup (MySQL, migrations, initial admin), build report with installed versions and limitations | All gates pass; report delivered |

---

## 10. Risks and mitigations

| # | Risk | Impact | Mitigation |
|---|---|---|---|
| R1 | Several "latest" versions are unconfirmed (Select2, SweetAlert2, Vue, AdminLTE) | Wrong or incompatible install | Re-check each version at P1 (§4). Record installed versions |
| R2 | Rich-text editor licensing or cloud-key requirements | Cost or a blocked editor | Choose a self-hosted editor after checking its licence (D2) |
| R3 | Select2 is a jQuery plugin and can conflict with Vue reactivity and Bootstrap modals | Broken dropdowns in modals | One shared wrapper component. Initialize inside the modal with `dropdownParent`. Test in P6 |
| R4 | Stored HTML could carry script (XSS) | Account compromise | Sanitize on write and render. Test with malicious payloads |
| R5 | Duplicate member IDs under concurrent approvals | Data integrity | Row-locked sequence + unique index + parallel test |
| R6 | Personal data handling under PDPA | Compliance | Consent record, minimal fields, legal review of wording (§7.5) |
| R7 | Scope creep into future modules | Delay, complexity | Out-of-scope list (§1). Future modules only as extension points |
| R8 | Mixing Blade and Vue state (e.g., modal data) | Stale UI | Keep Vue islands small. Server is source of truth. Reload data after writes |

---

## 11. Decisions needed from you (approval)

Please reply to each item. Proposed defaults are in **bold**. If you do not answer, I will use the default and list it in the build report.

| # | Question | Options | Proposed default |
|---|---|---|---|
| **D1** | Confirm the stack in §4 and whether it may use the newest versions found, or whether you prefer older ones | Confirm / change | **Confirm; re-check versions at P1** |
| **D2** | Rich-text editor | TinyMCE (self-hosted) · CKEditor 5 · Quill | **Self-hosted editor chosen after licence check; I will recommend one at P1** |
| **D3** | Committee roles | (a) admin + committee only · (b) admin + committee + viewer | **(b)** — position name edits and user management are admin-only |
| **D4** | Business category list | (a) I propose a starter list in Bahasa Malaysia · (b) you supply the official list | **(a)**, editable by admin later. Please confirm or replace |
| **D5** | Member ID format | `DPPA-YYYY-NNNN` · other format | **`DPPA-YYYY-NNNN`** |
| **D6** | Duplicate handling | Block on SSM only · warn on SSM/email/phone · block on any | **Block on same SSM; warn on same email or phone** |
| **D7** | Member list implementation | Server-side DataTables · Vue table with server-side query | **Vue table with server-side query** (matches Vue requirement) |
| **D8** | CSV export scope | Current filter only · full list | **Current filter result** |
| **D9** | Deletion | Soft delete only · permanent delete allowed for admin | **Soft delete only** in v1 |
| **D10** | Status log note on approve | Optional note · required note on reject | **Optional on approve, required on reject** |
| **D11** | Position holders | Multiple people per position allowed · one current holder per position | **Multiple allowed** (e.g., several Committee Members). Confirm that President/Treasurer should be single-holder |
| **D12** | Initial admin account | Artisan command prompts for name/email/password · seeded from `.env` values | **Artisan command prompt** (no credentials in source) |
| **D13** | Hosting target | Local only for v1 · shared hosting · VPS | **Local first; deploy config is a separate T2 task** |

---

## 12. Acceptance criteria (from the brief)

The v1 is complete when:

1. A visitor can submit a membership application, which is saved as **Pending** with a confirmation message.
2. An authorized committee user can log in and review, approve or reject it.
3. Approved members receive a unique member ID.
4. The committee can search and manage member details and chamber positions, including term dates and past appointments, from the AdminLTE area.
5. Admin forms open as popup modals. Select fields use Select2. Textareas use a rich-text editor. Data-changing actions ask for SweetAlert2 confirmation.
6. Data is stored in MySQL, configured by environment variables, with migrations and documented setup.

---

## 13. What I need from you

1. Approve this plan, or mark changes.
2. Answer D1–D13 in §11 (or accept the proposed defaults).
3. Confirm the project location for the Laravel app (proposed: this project root).

After approval I start at **P1**. I will not begin any build step before you approve.

---

## 14. Build status (v1, 2026-11-21)

### 14.1 Installed versions

| Component | Installed | Note |
|---|---|---|
| Laravel framework | 13.43.0 | Newer than the 13.12.0 candidate in §4 |
| PHP | 8.4.10 | Laravel 13 minimum is 8.3 |
| Vue.js | 3.5.46 | Latest stable 3.5 line |
| AdminLTE | 4.10.2 | Newer than the 4.3.1 candidate in §4 |
| Bootstrap | 5.3.9 | Matches the AdminLTE 4.10.2 range (`^5.3.8`) |
| jQuery | 3.8.0 | Pinned to jQuery 3 because Select2 does not support jQuery 4 |
| Select2 | **4.0.13** | **Deviation:** 4.1.0 is the newest release, but its AJAX results break (see 14.2) |
| select2-bootstrap-5-theme | 1.3.0 | |
| SweetAlert2 | 11.26.25 | |
| Quill | **2.0.2** | **Deviation:** 2.0.3 has an advisory with no patched release (see 14.2) |
| HTMLPurifier (`ezyang/htmlpurifier`) | 4.19 | Rich-text sanitization |
| Laravel Boost | 2.x (dev only) | Installed because AGENTS.md requires it |
| Database | MariaDB 10.4.28 | **Deviation:** MySQL 8.4 was the plan. The local server is MariaDB 10.4 from XAMPP. The app uses the MySQL driver and needs no change for MySQL 8 |

### 14.2 Deviations from the plan

| # | Plan said | What was done | Why |
|---|---|---|---|
| 1 | Laravel Breeze for login | Custom login controller and Blade view (`auth/login`) | Breeze's Blade stack uses Tailwind, which would clash with AdminLTE/Bootstrap. The login needs only email, password and throttling |
| 2 | Select2 latest stable (4.1.0) | Select2 4.0.13 | Select2 4.1.0 calls `AjaxAdapter._normalizeItem` without a bound `this`, so every AJAX result throws. Confirmed in the browser. Re-check when a fixed 4.1.x is released |
| 3 | Rich-text editor to be chosen (D2) | Quill, self-hosted, MIT/BSD licence | No cloud key or paid licence. Defaults were approved |
| 4 | Quill latest | Quill 2.0.2 | `npm audit` flagged 2.0.3 (GHSA-v3m3-f69x-jf25) with no patched release |
| 5 | Business address as a text area | Single-line input | Address is short. Every textarea is rich text per the brief, and this field is not one |
| 6 | Vue table with server-side query (D7) | Implemented as planned | — |
| 7 | Decisions D1–D13 | Approved defaults used as written | User approved the plan |

### 14.3 What was built

- **Public:** `/register` form (Select2 category, Quill for both text areas, consent, honeypot, throttled), success page at `/register/success`.
- **Auth:** `/login` and logout, with login throttling. Three roles (admin, committee, viewer) enforced by gates and route middleware.
- **Member database (`/admin/members`):** search, filters (status, category, product/service text), sort, pagination, CSV export, loading/empty/error states.
- **Member detail (`/admin/members/{id}`):** edit modal, review modal (approve / reject / pending, with reason required for rejection), duplicate warning, status history, appointments.
- **Positions (`/admin/positions`):** position names (admin), current holders, appointment history with term dates and current flag, assign and edit appointments in modals.
- **Categories (`/admin/categories`):** admin add/edit, deactivate instead of delete.
- **Console:** `php artisan pa:create-user` creates committee accounts. No credentials in source.
- **Data:** migrations for all tables, seeders for positions and categories, README setup steps.

### 14.4 Verification

| Check | Result |
|---|---|
| PHPUnit / `php artisan test` | 71 tests, 250 assertions, all passing. Three repeat runs also passed |
| Browser end-to-end (headless Chrome, scripted) | 8 of 8 flows passing, 0 browser errors: public submit, login, approval with SweetAlert2 confirm, cancelled edit does not save, confirmed edit saves, Select2 AJAX member search in a modal, appointment saved, CSV download |
| Pint (style) | Passing |
| `npm audit --omit=dev` | 0 vulnerabilities |
| `composer audit` | No advisories |
| Vite production build | Passing |
| CS `coresentinel verify` | Test check passed on the last run. Two earlier runs failed the same check, and I could not reproduce the cause (three direct PHPUnit runs and a direct CS-equivalent subprocess call all passed). Git-diff and anti-pattern checks show UNKNOWN because nothing has been committed |

**Not yet done:**
- No git commit has been made. Commit only when you ask.
- Mobile layout was checked on the public registration page only. The committee screens were checked at desktop width.
- The CS T2 gate is 17 squad agents plus the phase gates. I ran the automated checks above. The independent squad reviews (Cato, Argus, Echo, and others) have not been run. That needs your decision before release.
- Production deployment, HTTPS, backups and server hardening are out of scope for v1.

---

## 15. Account model and invitations (v2 change, 2026-11-21)

### 15.1 Design

| Concept | Meaning | Assigned by |
|---|---|---|
| Account type | `superadmin` (developer), `admin`, `staff` | Created by `pa:create-user` (superadmin only); admins are created by the superadmin; staff by admin or superadmin |
| Access role (staff only) | `committee` (full member and appointment access) or `viewer` (read-only, no applicant data) | Admin or superadmin |
| Account position (management hierarchy) | Named position with a level; 1 is highest | Superadmin for any position; admin only for positions below its own level, and only to staff |
| Invitation permission | `can_invite` flag on staff accounts. Management accounts can always invite | Admin or superadmin |

Role and position are separate fields, so access and rank can change independently. Superadmin and admin accounts have full access and no access role.

### 15.2 Rules enforced on the server

- Only the superadmin may create or change admin accounts. Admins may manage staff only.
- An admin may manage only staff whose position ranks below the admin's own (or who have no position). Nobody manages their own account through the accounts screen. Superadmin accounts cannot be changed through the interface.
- A deactivated account cannot log in. An existing session ends on its next request.
- Invitations: a registration link is single-purpose, expires (1 to 30 days, default 7), and has a usage limit (1 to 20). It is locked and counted in one transaction, so it cannot be over-used. Registration without a link is closed. Revoked, expired or used-up links show an invalid-link page.
- Members registered through a link keep a reference to it (`members.invitation_id`).

### 15.3 Starting values to confirm

- Account positions: Pengerusi (1), Timbalan Pengerusi (2), Setiausaha (3), Bendahari (4), Ahli Jawatankuasa (5). These are placeholders. The superadmin can rename or add positions.
- Invitation defaults: 7 days, one use.

### 15.4 Not yet built

- Self-service password change and password reset by email (no mail service is configured).
- Deactivating a staff account does not revoke invitations it created. They remain usable until they expire.
- The formal squad security re-review of the new account and invitation code has not been run.

---

## 16. Enhancements after v1 (2026-11-21)

Each item below was requested after the v1 build and is implemented and tested. Decisions the user did not specify are marked **(assumption)** so they can be changed.

### 16.1 Account model and permissions

- **Account types:** `superadmin` (developer), `admin`, `staff`. The superadmin is created with `pa:create-user --account-type=superadmin`. Admins are created by the superadmin; staff by admin or superadmin.
- **Access role (staff only):** `committee` (full member and appointment access) or `viewer` (no applicant data). Admin and superadmin have full access.
- **Management position (staff only):** a named position with a level (1 = highest). A superadmin can assign any position. An admin can assign only positions below its own, and only to staff. **(assumption)** The starting positions (Pengerusi, Timbalan Pengerusi, Setiausaha, Bendahari, Ahli Jawatankuasa) are placeholders.
- **Deactivation:** a deactivated account cannot log in, and an existing session ends on its next request.
- **Hierarchy rules:** nobody changes their own account through the accounts screen; superadmin accounts are not editable through the interface.

### 16.2 Registration invitations

- Registration is by invitation link only: `/register/{token}`. A link has an expiry (1–30 days, default 7) and a usage limit (1–20, default 1). It is locked and counted in one database transaction, so it cannot be over-used.
- `/register` without a link shows a closed page.
- Members who register through a link keep a reference to it.
- **Who can invite:** superadmin, admin, and staff marked "can invite". Staff see only their own invitations; management sees all.
- **Registration switch (admin and superadmin):** turning it off refuses every invitation link, including unexpired ones. Turning it on restores the links that are still valid. Registration is open by default.

### 16.3 Dashboard (landing page after login)

- KPI cards (members and applications, awaiting review, approved, current positions), each linking to the filtered list.
- Charts (Chart.js 4.6.1): applications and approvals per month (6 or 12 months), status doughnut (click a slice to open that filter), top business categories.
- Recent applications, recent status activity, and positions ending within 60 days.
- Auto-refresh every 60 seconds with a countdown and a manual refresh button; timers stop when the user navigates away.
- Viewer accounts see position counts only.

### 16.4 Language

- English is the default. Bahasa Melayu is available from the header menu, stored in the session.
- Translations live in `lang/en/app.php` and `lang/ms/app.php` with identical keys, and are used by Blade, server-side labels, and the Vue screens.
- Language changes reload the page, which keeps the document language correct.
- **Not translated yet:** the login page, public registration pages, CSV column headers, console command prompts, and Laravel's built-in validation messages (English in both languages). Public pages are in Bahasa Melayu by design.

### 16.5 Sidebar

- Every menu item has an inline SVG icon (no icon package is installed).
- **Submenus:** Member Database (All, Pending review, Approved, Rejected), Chamber Positions (Positions, Current holders, Appointment history), System Accounts (Accounts, Management positions). A submenu opens only for the section being viewed.
- **Counter badges:** pending applications on Member Database; active invitation links on Registration Invitations.
- The menu is built on the server (`App\Services\SidebarMenu`), so visibility and badge counts follow the same permission rules as the pages.
- The logo, when uploaded, fills the sidebar width with no padding and replaces the text name.

### 16.6 Settings (management accounts)

- **Email (Resend):** API key (encrypted at rest, never shown again), sender email, sender name. One Save for the card and a Test button that sends a real email through `POST https://api.resend.com/emails`.
- **WhatsApp (OnSend):** API key, base URL, sender ID are stored with one Save for the card. **Sending and testing are not connected**: the OnSend API reference is needed to confirm the endpoint and message format.
- **System logo (superadmin only):** drag-and-drop upload. The browser redraws the image on a canvas (at most 512 × 512 PNG) before upload. Stored on the public disk; replaces the name in the sidebar and public header.
- **Storage:** `app_settings` table; secrets are encrypted with the application key.

### 16.7 URLs

All committee and public addresses use English words: `/admin/members`, `/admin/positions`, `/admin/categories`, `/admin/accounts`, `/admin/invitations`, `/admin/settings`, `/register`, `/register/success`, `/login`, `/logout`. Old Malay addresses are not redirected.

### 16.8 Interface details

- Turbo (Hotwire 8.0.23) navigation: sidebar and in-app links change pages without a full reload. Public pages and logout use normal page loads.
- Modal, confirmation (SweetAlert2), and Select2 behaviour are unchanged from v1.

### 16.9 Verification at the end of this change set

- PHP tests: 131 passing (feature tests for accounts, invitations, registration switch, dashboard, language, settings, branding, sidebar, and the earlier v1 rules).
- Browser flow: 15 steps passing with no page errors (login, sidebar, language switch, invitation and registration, approval, edit, appointment, account and viewer, CSV export, dashboard).
- `coresentinel verify`: 100/100 at the last run.

### 16.10 Open items

1. **OnSend WhatsApp:** connect sending and testing once the API reference is provided.
2. **Security re-review:** the account, invitation, settings, file upload (logo), and registration switch code has not had the squad security review. Recommended before production.
3. **Public and login pages:** translate to English if required.
4. **Account self-service:** no password change or reset by email (no mail service configured beyond Resend).
5. **Invitation cleanup:** links created by a deactivated account stay usable until they expire.
6. **Retention:** the retention period for rejected applications still needs the chamber's decision.
7. **Deployment:** hosting, HTTPS, backups, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, trusted proxies, and `php artisan storage:link` on the server.
