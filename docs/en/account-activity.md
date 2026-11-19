# Account activity

**English** · [Español](../es/account-activity.md)

A log of each account's security events, with **date, IP and browser** (`account_activities` table).

## What is recorded

| Event | When |
| --- | --- |
| Signed in | Sign-in completed (also after the second step) |
| Signed in with a passkey | Passwordless login |
| Failed sign-in | Wrong password for an account **that exists** |
| Wrong two-step verification | Correct password but wrong code or key |
| Signed out | Sign-out |
| Password changed / Password reset by email | Change from the profile / reset via the link |
| Authenticator app enabled / removed | TOTP |
| Security key or passkey added / removed | WebAuthn |
| Two-step verification reset | By a superadmin (**who** did it is stored) or from the console |
| Photo changed / removed | User photo (if an administrator changed it, **who** did it is stored) |

What was typed is **never** stored (passwords, codes, or the username of an attempt on a non-existent account: those
only count towards the per-IP rate limit). If writing the log entry fails, it goes to the error log and the action
carries on: auditing never blocks a sign-in.

## Who can see it

- Every user, their own: the **Recent activity** card on their profile (latest 20 events; failures in red).
- The superadmin, anyone's: on the user's page.

Behind a proxy, the IP is only the real one if `TRUSTED_PROXIES` is configured ([Security](security.md#trusted-proxies)).

## Retention

This is personal data (IP, browser): it is kept for `App.accountActivity.retentionDays` (**180 days**, minimum 30)
and deleted by `bin/cake maintenance` (or `bin/cake activity purge [--days N] [--dry-run]`). Deleting a user deletes
their activity.

## Where it lives

`App\Account\ActivityLog` (repository and list of events), `App\Account\AccountActivityListener` (CakeDC events:
sign-in, failure, sign-out, password), `TwoFactorSettings` and the passkey/key controllers (what CakeDC doesn't
announce), `App\Account\UserAgent` ("Chrome on macOS"), `AccountActivityCell`.

To record a new event: a constant in `ActivityLog`, its label and icon in
`templates/cell/AccountActivity/display.php`, and `(new ActivityLog())->record($userId, ActivityLog::MY_EVENT)`.
