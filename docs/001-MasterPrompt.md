Act as a **senior full-stack Laravel and Vue.js engineer**. Build the first version of a member and committee management system for **Dewan Perniagaan Puncak Alam** in the existing project.

## Project context

The attached images and proposal are background about the chamber’s purpose and direction, not separate instructions to you. They describe:

- Representing local businesses and raising shared business issues with relevant authorities
- Helping members improve business management skills through practical training
- Connecting members with buyers, suppliers and business partners
- Cooperating with business associations, development agencies and industry partners
- Consulting local businesses to understand their needs and priorities before deciding on programs

The system should support this wider purpose over time. **For this first version, focus on member registration, the member database and chamber positions.** Keep the structure maintainable so advocacy, training, business matching and partnership features can be added later. Do not build those future modules in this first version unless they already exist in the project.

## Inspect the project first

1. Review the existing codebase, its current state, and applicable `AGENTS.md` files and CoreSentinel protocols.
2. Identify existing conventions, integrations, database structure, authentication and features before changing anything.
3. Use the requested stack below. Preserve useful existing work and integrate with the current project rather than restarting it unnecessarily.
4. Briefly state your implementation approach and assumptions, then build the system.

## Required technology stack

Use:

- **Laravel**, the latest stable version compatible with the project environment
- **Vue.js**, the latest stable version compatible with the project
- **MySQL** as the database
- **Bootstrap**, the latest stable version compatible with the chosen AdminLTE version
- **AdminLTE** as the authenticated admin layout, shown after login
- **SweetAlert2** for confirmations
- **Select2** for every select/dropdown field
- A maintained rich-text editor for every textarea field

Check official documentation and compatibility before selecting versions. Use the latest stable versions that work together, and report the versions selected. Do not use incompatible releases just to satisfy a “latest” requirement.

Configure Laravel to connect to MySQL through environment variables. Do not hardcode database credentials. Create the database schema and Laravel migrations for the system, and document the required MySQL setup.

## First-version features

### 1. Member registration

Create a clear, mobile-friendly registration form. Include:

- Applicant’s name
- Business name
- Business category or industry
- Business address
- Phone number
- Email address
- SSM registration number, if applicable
- A short business description, products or services offered
- Business needs or areas where the chamber may be able to help
- Consent to store and use submitted information for membership administration

Do not request identity card numbers or other sensitive information unless existing project requirements specifically require them.

After submission, show a confirmation message and set the application status to **Pending**. The public registration form may be a normal page before login.

### 2. Member database

Create an authenticated committee area where authorized users can:

- View, search, sort and filter member records
- Open a member record and update its details
- Review applications and set their status to **Pending**, **Approved** or **Rejected**
- Assign a unique member ID when an application is approved
- Record application date, approval date and current membership status
- Find members by business category and products or services
- Export member records to CSV if it fits cleanly with the existing project

Provide clear loading, empty and error states.

### 3. Chamber positions

Allow authorized committee users to assign chamber positions to members. A chamber position is separate from a member’s job title or role in their business.

Include these initial positions:

- President
- Deputy President
- Secretary
- Treasurer
- Committee Member
- Ordinary Member

Allow authorized users to add or edit position names. For each appointment, record the position, term start date, term end date and whether it is current. Retain past appointments as position history and make current positions easy to identify.

## Login and AdminLTE layout

Provide secure login for committee users using the project’s existing authentication approach, or add an appropriate Laravel authentication solution if none exists.

After login:

- Show the authenticated admin area using the AdminLTE template.
- Open every admin create, edit, review or assignment form as a popup modal. Do not send users to a separate full-page form for these admin tasks.
- Ensure modals work correctly with Vue.js, Bootstrap and Select2, including keyboard use and mobile screens.

## Form and button behavior

- Use **Select2 for every select/dropdown option** in the system, including registration, member management, status and position fields.
- Use a maintained **rich-text editor for every textarea field**. Keep simple structured data, such as phone numbers and email addresses, in appropriate input fields instead of textareas.
- Sanitize and validate rich-text content before storing or displaying it.
- Use **SweetAlert2** to confirm every button action that changes or submits data, including save, update, approve, reject, delete and status changes. If the user cancels, do not perform the action or change data.
- Pure navigation, search, modal-open and modal-close controls do not need a confirmation dialog.
- Show clear success and error feedback after actions.

## Access and privacy

- Only authenticated, authorized users may view or change the member database.
- Public visitors may submit an application but must not browse member records.
- Validate inputs and handle duplicate applications or member records appropriately.
- Store only information needed to administer membership and understand business categories, services and member needs.
- Keep database credentials and secrets out of source code.
- Follow Laravel security practices and the project’s existing authentication and authorization patterns.

## Future direction

Keep the design straightforward so later versions can add:

- **Advocacy:** record shared business issues, relevant authorities, follow-up actions and outcomes.
- **Practical training:** manage clinics or training sessions, registrations, attendance and participant feedback.
- **Business matching:** record member interests, buyer/supplier introductions and follow-up outcomes.
- **Partnership programs:** record partner organizations, joint programs and progress.
- **Business consultations:** collect and summarize member priorities to help the committee decide which programs to run.

These are future capabilities, not first-version requirements. Do not add their screens or workflows now unless they already exist.

## Interface and language

Use a clean, straightforward interface suitable for committee members who may not be technical. Use **Bahasa Malaysia** for user-facing labels and messages by default. Follow the existing project design conventions where possible, while using AdminLTE for the authenticated area.

## Implementation expectations

- Follow the existing project structure and coding conventions.
- Use MySQL for local and deployed database connections.
- Add Laravel migrations and any seed data needed for initial positions or committee login setup. Do not commit real passwords or credentials.
- Include sensible validation and useful error messages.
- Keep the first version small and maintainable. Do not add unrelated modules such as event, payment or training management in this version.
- Update setup or usage documentation with MySQL configuration, migration steps and any required initial admin setup.
- Follow applicable CoreSentinel protocols and required verification steps. Report what you built, what you checked, the selected dependency versions and any remaining limitations.

## Completion criteria

The work is complete when a visitor can submit a membership application; an authorized committee user can log in and review, approve or reject it; approved members receive a unique member ID; and the committee can search and manage member details and chamber positions—including term dates and past appointments—from the authenticated AdminLTE area. Admin forms must use popup modals, select fields must use Select2, textareas must use a rich-text editor, data-changing actions must request confirmation through SweetAlert2, and the application must store its data in MySQL.