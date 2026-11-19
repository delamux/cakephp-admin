# Accounts, users and roles

Accounts are managed by [CakeDC/Users](https://github.com/CakeDC/users) (fork `delamux/CakeDC-users` for
webauthn-lib 5), with its screens overridden in `templates/plugin/CakeDC/Users/Users/` and its config in
`config/users.php`.

## What a person can do

| Feature | Details |
| --- | --- |
| Sign up | `/register`, with email verification (`Users.Email.validate`); default role `user`. Disabled with `Users.Registration.active` |
| Sign in | Username **or** email + password; "remember me" with an encrypted cookie; second step if 2FA is enabled |
| Passkey | "Sign in with a passkey" on the login page, no password ([2FA and passkeys](two-factor.md)) |
| Magic link | "Email me a sign-in link" (`OneTimeLogin`): single-use link by email; it also asks for 2FA |
| Password recovery | "Forgot your password?": token link by email |
| Profile | `/profile`: details, **photo**, change password, two-step verification and **recent activity** |
| Change password | Asks for the current one; recorded in their [activity](account-activity.md) |

Every email goes out through the [queue](queue.md) and uses the [app layout](emails-and-pdf.md). Public forms are
[rate-limited per IP](security.md).

## Users (CRUD)

*Administration › Users*: list with search, sorting and pagination without page reloads (htmx), user page, create,
edit and delete. On the user page the superadmin also sees the 2FA status (with a reset button) and the account
activity.

Rules (`App\Service\UserRoleManager`, with tests):
- Only a **superadmin** manages other superadmins and grants or revokes that role (an admin who changed a
  superadmin's email could take over their account with a reset).
- **Nobody changes their own role** (there's always at least one administrator: the one acting) or deletes their own
  account.
- Another user's password can only be changed by a superadmin, and only if `Users.Superuser.allowedToChangePasswords`
  is enabled.
- The `superadmin` role is kept in sync with CakeDC's `is_superuser` flag.

## Roles

*Access control › Roles* (superadmin only): CRUD of roles in the `roles` table.

| Role | Can do by default |
| --- | --- |
| `superadmin` | Everything (bypasses the RBAC). Protected: cannot be deleted or renamed |
| `admin` | Manage users (except superadmins) and everything else except *Access control* and the queue |
| `editor` | View users and the PDF report |
| `user` | Dashboard and their profile |

A role with assigned users can't be deleted. What each role can do is changed in the
[permissions matrix](permissions.md); role changes are recorded in the permissions history.

## Dashboard and report

- **Dashboard** (`/`): KPIs (total, active, pending, admins, sign-ups in the last 30 days vs the previous 30), a daily
  sign-ups chart, latest users and the team. A regular user sees a welcome card.
- **Users report as PDF** (`/reports/users.pdf`, `?download=1` to download): see [Emails and PDF](emails-and-pdf.md).

## Commands

```bash
bin/cake users add_superuser -u admin -p 'Secret1234!' -e admin@example.com
bin/cake seeds run DemoUsers          # 40 demo users (password Demo1234!), development only
```

> CakeDC's `bin/cake users password_email` **doesn't send** the email (it doesn't pass the type to `resetToken()`):
> for a reset use "Forgot your password?".
