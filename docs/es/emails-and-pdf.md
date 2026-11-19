# Emails y PDF

[English](../en/emails-and-pdf.md) · **Español**

## Emails

- **Layout propio** (`templates/layout/email/`): tablas a 600 px, preheader, botón «bulletproof», ajustes para
  Outlook (MSO), modo oscuro y móvil como mejora progresiva, versión de texto en todos.
- **CSS en línea** con Emogrifier (`App\Mailer\CssInliner` + `InlineCssTrait`) antes de enviar: los clientes de
  correo que ignoran `<style>` ven el mismo diseño (html-check de Mailpit: ~90 % soportado).
- **Mailers:** `App\Mailer\UsersMailer` (todos los emails de cuentas de CakeDC con asuntos propios: validación,
  reset, enlace mágico, cuentas sociales, avisos de 2FA) y `AppMailer` como base para los tuyos.
- **Envío:** por la [cola](queue.md) (`Email.default` → transporte `queue` → SMTP real). En dev todo llega a Mailpit
  (http://localhost:8026).
- Remitente: `EMAIL_FROM` y `EMAIL_FROM_NAME`; SMTP: `EMAIL_TRANSPORT_DEFAULT_URL`
  (`smtp://usuario:clave@host:587?tls=true`).

Un email nuevo: un mailer que herede de `AppMailer` (p. ej. `App\Mailer\CustomersMailer` con un método `welcome()`
que haga `$this->setEmailFormat('both')->viewBuilder()->setTemplate('welcome')`), sus plantillas en
`templates/email/html/` y `templates/email/text/`, y `$this->getMailer('Customers')->send('welcome', [$customer])`.
Sale por la cola sin hacer nada más.

## PDF

[CakePdf](https://github.com/FriendsOfCake/CakePdf) con **Dompdf** (PHP puro, sin binarios en la imagen).

- **Informe de usuarios:** `/reports/users.pdf` (staff con permiso `Reports::users`): KPIs + tabla.
  `?download=1` fuerza la descarga como `users-report-AAAAMMDD-HHMMSS.pdf`.
- Un PDF nuevo: acción con extensión `pdf` en un controlador y su plantilla en `templates/<Controlador>/pdf/`.
  Dompdf no soporta `counter(pages)`: el pie usa «Page N».
- Se genera en la petición (al momento). Para informes pesados, genéralo en una
  [tarea de la cola](queue.md#crear-una-tarea-propia) y avisa o deja la descarga cuando esté.
