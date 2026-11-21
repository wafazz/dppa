# Dewan Perniagaan Puncak Alam — Member & Committee Management

A web system for the Dewan Perniagaan Puncak Alam (Puncak Alam Chamber of Commerce). It handles membership applications from businesses, the committee's member database, chamber positions and their history, and the system accounts that run it.

**Stack:** Laravel 13 · Vue 3 · AdminLTE 4 (Bootstrap 5) · MySQL/MariaDB · Chart.js · Select2 · Quill · SweetAlert2 · Turbo

Scope and decisions are recorded in [Planning.md](Planning.md).

---

## Contents

- [What it does](#what-it-does)
- [Pages and addresses](#pages-and-addresses)
- [Accounts and permissions](#accounts-and-permissions)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Settings](#settings)
- [Commands](#commands)
- [Testing](#testing)
- [Project layout](#project-layout)
- [Deployment checklist](#deployment-checklist)
- [Limitations and next steps](#limitations-and-next-steps)

---

## What it does

**For applicants (public)**
- Apply for membership through a personal invitation link. The form asks only for what membership administration needs, records consent, and shows a confirmation.
- Confirm the e-mail address on the application through a link that expires after 72 hours. A confirmation e-mail follows once the address is confirmed.
- The chamber can close registration at any time. While it is closed, every link stops working.

**For the committee (after sign-in)**
- **Dashboard:** KPI cards, monthly applications and approvals, status breakdown, top business categories, recent activity, and terms ending soon. Refreshes automatically.
- **Member database:** search, filters (status, category, products or services), sorting, pagination, CSV export. Review applications (approve, reject, return to pending), edit details, see possible duplicates, and read the full status history.
- **Member numbers:** approved members receive a unique number such as `DPPA-2026-0001`, which is never reused.
- **Chamber positions:** position names, current holders, and an appointment history with term dates. Past appointments are ended, never deleted.
- **Business categories:** the list offered on the application form.
- **Invitations:** create and revoke registration links, with expiry and usage limits. Switch public registration on or off.
- **System accounts:** create admins and staff, set access roles and management positions, and deactivate accounts.
- **Settings:** email (Resend) and WhatsApp (OnSend) credentials, and the system logo.

**Usability**
- English by default, with Bahasa Melayu available from the header.
- Responsive layout for phones and desktops, keyboard-friendly dialogs, and a sidebar with submenus and counter badges.

---

## Pages and addresses

| Address | Purpose | Who |
|---|---|---|
| `/register/{token}` | Application form through an invitation link | Anyone with a valid link |
| `/register` | Shows that registration is by invitation only | Public |
| `/login` | Committee sign-in | Public |
| `/admin` | Dashboard (first page after sign-in) | Any signed-in account |
| `/admin/members` | Member database | Admin, superadmin, committee |
| `/admin/members/{id}` | Member detail, review, edit, appointments | Admin, superadmin, committee |
| `/admin/positions` | Chamber positions, current holders, history | All signed-in accounts |
| `/admin/categories` | Business categories | All signed-in accounts (edit: admin, superadmin) |
| `/admin/accounts` | System accounts and management positions | Admin, superadmin |
| `/admin/invitations` | Invitation links and the registration switch | Admin, superadmin, staff who can invite |
| `/admin/settings` | Email and WhatsApp settings, system logo | Admin, superadmin (logo: superadmin only) |

---

## Accounts and permissions

Accounts have a **type** and, for staff, an **access role** and an optional **management position**. These are separate on purpose: what a person may use and where they sit in the hierarchy can change independently.

| Account type | Who | Created by |
|---|---|---|
| `superadmin` | The developer. Full access, including the system logo | `pa:create-user --account-type=superadmin` on the server |
| `admin` | Chamber administrators. Full access except the logo | Superadmin |
| `staff` | Committee members and viewers | Admin or superadmin |

| Staff access role | Member records | Create and change appointments | Export CSV | Positions and categories | Invitations |
|---|---|---|---|---|---|
| Committee | Full | Yes | Yes | View | If "can invite" is set |
| Viewer | Not visible | No | No | View | If "can invite" is set |

**Management positions** (for example Pengerusi or Setiausaha) follow a level order where 1 is highest. A superadmin can give any position. An admin can give only positions below its own, and only to staff. Nobody changes their own account from the accounts screen. A deactivated account cannot sign in, and an open session ends on its next request.

---

## Requirements

| Software | Version used during development |
|---|---|
| PHP 8.3+ with `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `curl`, `xml`, `bcmath`, `sodium` | 8.4.10 |
| Composer | 2.8 |
| Node.js (current LTS) and npm | Node 24, npm 11 |
| MySQL 8 or MariaDB 10.4+ | MariaDB 10.4.28 |

**Backend:** Laravel framework 13.43 · HTMLPurifier 4.19 (rich-text cleaning) · Laravel Boost 2.10 (development tool, see `AGENTS.md`).

**Frontend:** Vue 3.5.46 · AdminLTE 4.10.2 · Bootstrap 5.3.9 · Chart.js 4.6.1 · Select2 4.0.13 · Quill 2.0.2 · SweetAlert2 11.26.25 · Hotwire Turbo 8.0.23 · jQuery 3.8.0.

> Select2 is pinned to 4.0.13 because 4.1.0 breaks AJAX results. Quill is pinned to 2.0.2 because 2.0.3 has an advisory with no patched release.

---

## Quick start

### 1. Create the databases

Use a dedicated database user, not `root`. Replace the password with your own.

```sql
CREATE DATABASE pa_entrepreneur CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE pa_entrepreneur_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pa_app'@'localhost' IDENTIFIED BY 'replace-with-a-strong-password';
GRANT ALL PRIVILEGES ON pa_entrepreneur.* TO 'pa_app'@'localhost';
GRANT ALL PRIVILEGES ON pa_entrepreneur_test.* TO 'pa_app'@'localhost';
```

`pa_entrepreneur_test` is used only by the automated tests, which reset it on every run.

### 2. Install and configure

```sh
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
APP_NAME="Dewan Perniagaan Puncak Alam"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Kuala_Lumpur

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pa_entrepreneur
DB_USERNAME=pa_app
DB_PASSWORD=replace-with-a-strong-password

SESSION_DRIVER=database
```

Never commit `.env`. Credentials are read from environment variables only.

Then install and prepare:

```sh
composer install
npm install
php artisan migrate --seed
php artisan storage:link
php artisan pa:create-user --account-type=superadmin
npm run build
php artisan serve
```

- `migrate --seed` creates the chamber positions, the starter business categories, and the management positions. It creates no accounts.
- `storage:link` is needed for the system logo.
- `pa:create-user` asks for the name, email, and a password of at least 12 characters. The password is never typed on the command line.

Open `http://localhost:8000/login`. During development, run `npm run dev` instead of `npm run build` for live reloading. If port 8000 is busy, use `php artisan serve --port=8001`.

---

## Settings

Settings are changed in the app, not in `.env`. They are stored in the `app_settings` table, and API keys are encrypted with `APP_KEY`. A saved key is never shown again.

| Setting | Who | Notes |
|---|---|---|
| Email (Resend): API key, sender email, sender name | Admin, superadmin | One Save for the card. **Test** sends a real email through `https://api.resend.com/emails`. The sender domain must be verified in Resend. |
| WhatsApp (OnSend): API key, base URL, sender ID | Admin, superadmin | Saved for later use. Sending and testing are not connected yet. |
| System logo | Superadmin only | Drag and drop a PNG. The browser resizes it to at most 512 × 512 before upload. Replaces the name in the sidebar and the public header. |
| E-mail wording | Admin, superadmin | Edit the wording (subject, text, button) of each automatic e-mail, in English or Bahasa Melayu. The logo and layout stay fixed. Under Settings > Email templates. |
| Announcements | Admin, superadmin | One e-mail to members who agreed at registration and whose address is verified. Each message has a link to stop receiving announcements. |
| Public registration on or off | Admin, superadmin | On the Invitations page. Off refuses every invitation link. |

**Keep `APP_KEY` safe.** Saved API keys can only be decrypted with it.

---

## Commands

| Command | Purpose |
|---|---|
| `php artisan pa:create-user` | Create a superadmin, admin, or staff account. Prompts for the password. Options: `--name`, `--email`, `--account-type=superadmin\|admin\|staff`, `--role=committee\|viewer` |
| `php artisan pa:reset-password --email=you@example.com` | Set a new password for an account, for example a locked-out superadmin. Prompts for the password twice. |
| `php artisan pa:send-email-reminders` | Send the due reminders and vote notices. Runs every five minutes from the scheduler |
| `php artisan pa:send-management-digest` | Send the weekly digest of waiting applications. Runs on Mondays at 08:00 |
| `php artisan migrate` | Apply database changes after updating the code |
| `php artisan storage:link` | Create the public storage link used by the logo |

---

## Testing

```sh
php artisan test
```

- The suite runs against `pa_entrepreneur_test`, configured in `phpunit.xml`. Each run resets that database, so never point it at real data.
- Coverage: registration and validation, invitations and the registration switch, sign-in and throttling, approval and member numbers, search and filters, positions and term rules, roles and permissions, accounts and hierarchy, dashboard figures, language switching, settings and the Resend requests, logo upload, the sidebar menu, CSV export, and rich-text cleaning.

---

## Project layout

```
app/
  Console/Commands/      pa:create-user, pa:reset-password
  Enums/                 AccountType, MemberStatus, UserRole
  Http/Controllers/      Public registration, sign-in, admin pages, JSON API (Api/)
  Http/Middleware/       SetLocale, EnsureAccountIsActive
  Http/Requests/         Form validation per action
  Http/Resources/        JSON shapes for the Vue screens
  Models/                Member, PositionAppointment, Invitation, User, ...
  Services/              Approval, duplicates, accounts, invitations, dashboard,
                         settings, Resend mailer, branding, sidebar menu
config/membership.php    Consent version and member number prefix
lang/en, lang/ms         Translations (same keys in both)
resources/js/
  pages/                 Vue pages (dashboard, members, positions, accounts, ...)
  components/            Dialogs, Select2 and rich-text wrappers, charts, logo upload
  lib/                   API helper, confirmations, formatting, translations
resources/views/         Blade layouts and pages
routes/web.php           All routes, with permission checks and throttling
tests/Feature/           Feature tests
```

---

## Deployment checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] A fresh `APP_KEY` on the server, backed up somewhere safe
- [ ] `SESSION_SECURE_COOKIE=true`, served over HTTPS only
- [ ] Trusted proxies configured if the app sits behind a reverse proxy (per-IP limits depend on it)
- [ ] `php artisan migrate --force`, then `php artisan storage:link`
- [ ] `npm run build` on the build machine, with `public/build` deployed
- [ ] A superadmin created with `pa:create-user`, and the Resend sender domain verified
- [ ] Database backups scheduled
- [ ] A cron entry that runs `php artisan schedule:run` every minute (verification reminders, term endings, vote notices, weekly digest)
- [ ] Squad security review of the account, invitation, settings, and upload code (see Planning.md §16.10)

---

## Limitations and next steps

- **E-mail:** every message uses one template with the uploaded logo. Set-up links, verification links, and two-factor codes need Resend connected. Without it, the set-up link is shown once to the administrator, and the verification link can be sent later from the member record.
- **Existing applicants** from before e-mail verification start unverified. Use "Send verification e-mail again" on each member record.
- **WhatsApp (OnSend):** sending and testing wait for the OnSend API reference.
- **Public and sign-in pages** are in Bahasa Melayu. Laravel's built-in validation messages are in English.
- **Password reset by e-mail** needs Resend connected. Without it, use `pa:reset-password` on the server.
- **Invitations from deactivated accounts** stay usable until they expire.
- **Retention:** how long rejected applications are kept still needs the chamber's decision. Obligations under the Personal Data Protection Act 2010 (Malaysia) should be confirmed with a legal adviser.

See [Planning.md](Planning.md) for the full history, decisions, and open items.
