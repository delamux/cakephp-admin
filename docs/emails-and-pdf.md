# Emails and PDF

## Emails

- **Custom layout** (`templates/layout/email/`): 600 px tables, preheader, "bulletproof" button, Outlook (MSO)
  tweaks, dark mode and mobile as progressive enhancement, a plain-text version for every email.
- **Inline CSS** with Emogrifier (`App\Mailer\CssInliner` + `InlineCssTrait`) before sending: email clients that
  ignore `<style>` see the same design (Mailpit's html-check: ~90 % supported).
- **Mailers:** `App\Mailer\UsersMailer` (every CakeDC account email with custom subjects: validation, reset, magic
  link, social accounts, 2FA notifications) and `AppMailer` as the base for your own.
- **Sending:** through the [queue](queue.md) (`Email.default` → `queue` transport → real SMTP). In dev everything
  lands in Mailpit (http://localhost:8026).
- Sender: `EMAIL_FROM` and `EMAIL_FROM_NAME`; SMTP: `EMAIL_TRANSPORT_DEFAULT_URL`
  (`smtp://user:password@host:587?tls=true`).

A new email: a mailer extending `AppMailer` (e.g. `App\Mailer\CustomersMailer` with a `welcome()` method that does
`$this->setEmailFormat('both')->viewBuilder()->setTemplate('welcome')`), its templates in `templates/email/html/` and
`templates/email/text/`, and `$this->getMailer('Customers')->send('welcome', [$customer])`. It goes through the
queue with nothing else to do.

## PDF

[CakePdf](https://github.com/FriendsOfCake/CakePdf) with **Dompdf** (pure PHP, no binaries in the image).

- **Users report:** `/reports/users.pdf` (staff with the `Reports::users` permission): KPIs + table.
  `?download=1` forces the download as `users-report-YYYYMMDD-HHMMSS.pdf`.
- A new PDF: an action with the `pdf` extension in a controller and its template in `templates/<Controller>/pdf/`.
  Dompdf doesn't support `counter(pages)`: the footer uses "Page N".
- It's generated during the request (on the spot). For heavy reports, generate it in a
  [queue task](queue.md#creating-your-own-task) and notify the user or leave the download ready when it's done.
