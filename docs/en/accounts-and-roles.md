# Accounts, users and roles

**English** · [Español](../es/accounts-and-roles.md)

Accounts are managed by [CakeDC/Users](https://github.com/CakeDC/users) (fork `delamux/CakeDC-users` for
webauthn-lib 5), with its screens overridden in `templates/plugin/CakeDC/Users/Users/` and its config in
`config/users.php`.

## What a person can do

| Feature | Details |
| --- | --- |
| Sign up | `/register`, with email verification (`Users.Email.validate`); default role `user`. On by default; switch it off with `REGISTRATION_ENABLED=false` ([Security](security.md#public-sign-up)) |
| Sign in | Username **or** email + password; "remember me" with an encrypted cookie; second step if 2FA is enabled |
| Passkey | "Sign in with a passkey" on the login page, no password ([2FA and passkeys](two-factor.md)) |
| Magic link | "Email me a sign-in link" (`OneTimeLogin`): single-use link by email; it also asks for 2FA |
| Password recovery | "Forgot your password?": token link by email |
| Profile | `/profile` (your own only: another account's `/profile/<id>` shows yours): details, **photo**, change password, two-step verification, **where you are signed in** and **recent activity** |
| Change password | Asks for the current one; signs out the other sessions of the account ([Sessions](security.md#sessions)) and is recorded in their [activity](account-activity.md) |

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
- These protections (`AppController::beforeFilter`) hold for **every action on someone else's account**: edit,
  delete, change password and reset their 2FA, whatever the permissions matrix says.
- Editing a user **never** changes their password, even with a forged form (the CRUD drops `password`); the password
  only changes on its own screens (change password, reset by email, accepting an invitation). Tokens (validation,
  reset, sign-in link, API) and sign-in dates are not taken from any form.
- Adding, editing and deleting users ask to [confirm the password](security.md#sessions) unless it was typed in the
  last minutes.

## Invitations

*Administration › Users › Invite user*: enter the email, the role and (optionally) the name. The account is created
**inactive** and the person gets an email (through the queue) with a link to choose their password; until then they
cannot sign in. The list shows them as **Invited**, with **Resend invitation** in the row menu (new link, the old one
stops working).

- Only the roles the inviter may assign (an admin never invites a superadmin).
- The link is single use and expires after `App.invitations.expirationDays` (**7 days**, `config/users.php`).
- The accept page (`/invitations/accept/<token>`) is public and rate-limited per IP; passwords need at least 8 characters.
- Invitation (with **who** invited) and acceptance go to the [account activity](account-activity.md).
- Who can invite is a permission (`Invitations::add` / `resend`, admins by default) in the [matrix](permissions.md).

## Where you are signed in

The profile lists the account's **live sessions** (browser, IP, last activity; the current one marked as *This
device*). The user can sign out one of them or **every other session**. The latter also invalidates the "remember me"
cookies of those devices, so they cannot sign back in on their own (`users.session_key`, part of the cookie token). It
is recorded in the [activity](account-activity.md).

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
bin/cake users password_email ana@example.com   # email a password reset link
bin/cake seeds run DemoUsers          # 40 demo users (password Demo1234!), development only
```

`users password_email` is fixed in the CakeDC fork (it did not send the email).
