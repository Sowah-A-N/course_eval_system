# Mail Features — Setup & Implementation Guide

How the Course Evaluation System sends email, how to turn it on in each
environment, and how to extend it.

---

## 1. What sends email today

New users are emailed their login details automatically whenever an account is
created — at **all 7 creation points**:

| Where | Role | File |
|-------|------|------|
| Bulk student import | Secretary | `secretary/students/import.php` |
| Bulk lecturer import | Secretary | `secretary/lecturers/import.php` |
| Bulk student import | Admin | `admin/users/import_students.php` |
| Bulk lecturer import | Admin | `admin/users/import_lecturers.php` |
| Add single student | Secretary | `secretary/students/create.php` |
| Add single lecturer | Secretary | `secretary/lecturers/create.php` |
| Add single user (any role) | Admin | `admin/users/create.php` |

Each email contains: a link to the login page, the user's **username**, the
**email** they can also sign in with, their **starting password**, and a note
that they must change it on first login.

Password reset emails (`forgot_password.php`) use the same delivery approach.

---

## 2. How it works (architecture)

Everything funnels through one helper: **`includes/mailer.php`**.

```
account created ──► ces_send_login_details($email, $name, $username, $password)
                        │  builds the subject + plain-text body
                        ▼
                    ces_deliver_mail(...)  ──►  PHPMailer
                                                  ├─ SMTP_HOST set?  ► send over SMTP (recommended)
                                                  ├─ otherwise        ► PHP mail()
                                                  └─ library missing  ► raw mail() fallback
```

Key properties:

- **Best-effort.** Every function returns a boolean and never throws. If mail
  fails, account creation still succeeds and the credentials are still shown
  on screen to hand out manually. A failure is logged to the PHP error log
  (`[CES][mail] …`).
- **Library:** [PHPMailer](https://github.com/PHPMailer/PHPMailer) **6.12**,
  installed via Composer and committed under `vendor/` (production has no
  Composer, so the library ships with the code). Pinned to the 6.x line because
  PHPMailer 7.x requires PHP 8.0+ and production runs PHP 7.x.
- **Config is environment-driven** — the same code runs in every environment;
  you only set environment variables. No code edits to switch mail on/off.

Absolute links inside emails come from `ces_absolute_base_url()`, which prefers
`APP_URL` and otherwise builds `scheme://host` from the current request. (This
is separate from on-site redirects, which use relative paths.)

---

## 3. Configuration (environment variables)

Set these on the **server** (see section 4 for how). All are optional — with
none set, the app uses PHP's `mail()`.

| Variable | Purpose | Default |
|----------|---------|---------|
| `SMTP_HOST` | SMTP server hostname. **Leave unset to use PHP `mail()`.** | *(unset)* |
| `SMTP_PORT` | SMTP port | `587` |
| `SMTP_USER` | SMTP login username. Leave empty for no authentication. | *(empty)* |
| `SMTP_PASS` | SMTP login password | *(empty)* |
| `SMTP_SECURE` | `tls` (STARTTLS, port 587), `ssl` (SMTPS, port 465), or `none` | `tls` |
| `SMTP_FROM` | Sender address override | `SYSTEM_EMAIL_FROM` constant |
| `APP_URL` | Absolute site URL for the login link in emails | built from request |

The sender name and default sender address also come from constants in
`config/constants.php`: `SYSTEM_EMAIL_NAME`, `SYSTEM_EMAIL_FROM`, `APP_NAME`,
`INSTITUTION_NAME`.

> **Never commit real SMTP credentials.** They belong in server environment
> variables, exactly like the database password.

---

## 4. Setting the variables per environment

### Apache + mod_php (typical cPanel / WAMP)

In an `.htaccess` file at the site root, or a `<VirtualHost>` block:

```apache
SetEnv SMTP_HOST   smtp.yourprovider.com
SetEnv SMTP_PORT   587
SetEnv SMTP_USER   noreply@yourdomain.edu
SetEnv SMTP_PASS   your-smtp-password
SetEnv SMTP_SECURE tls
SetEnv SMTP_FROM   noreply@yourdomain.edu
SetEnv APP_URL     https://eval.yourdomain.edu
```

### Nginx + PHP-FPM

`SetEnv` does not exist in Nginx. Pass them in the FastCGI config:

```nginx
fastcgi_param SMTP_HOST   smtp.yourprovider.com;
fastcgi_param SMTP_PORT   587;
fastcgi_param SMTP_USER   noreply@yourdomain.edu;
fastcgi_param SMTP_PASS   your-smtp-password;
fastcgi_param SMTP_SECURE tls;
fastcgi_param APP_URL     https://eval.yourdomain.edu;
```

Or set them in the PHP-FPM pool (`www.conf`):

```
env[SMTP_HOST] = smtp.yourprovider.com
env[SMTP_USER] = noreply@yourdomain.edu
env[SMTP_PASS] = your-smtp-password
```

> If `getenv()` returns nothing after setting `SetEnv`, the host is likely using
> PHP-FPM/CGI rather than mod_php — use the FastCGI/pool method instead.

After changing server config, reload the web server (or restart WAMP).

### WAMP (local dev)

Local WAMP has no mail server, so `mail()` will not deliver. To test real
sending locally, point the `SMTP_*` variables at a real or test SMTP inbox
(e.g. Mailtrap, or a Gmail app password). Otherwise rely on the on-screen
credentials during development.

---

## 5. Common provider settings

| Provider | `SMTP_HOST` | `SMTP_PORT` | `SMTP_SECURE` | Notes |
|----------|-------------|-------------|---------------|-------|
| cPanel mail (same host) | `mail.yourdomain.edu` or `localhost` | `587` | `tls` | Create the mailbox in cPanel first |
| Gmail / Google Workspace | `smtp.gmail.com` | `587` | `tls` | Requires an **App Password**, not the account password |
| Microsoft 365 | `smtp.office365.com` | `587` | `tls` | User must be allowed to send |
| Mailtrap (testing) | `sandbox.smtp.mailtrap.io` | `587` | `tls` | Catches mail in a test inbox |

Port `465` → use `SMTP_SECURE=ssl`. Port `587` (or `25`) → use `tls`.

---

## 6. Testing delivery

1. Set the `SMTP_*` variables on the server and reload the web server.
2. Sign in as an Admin or Secretary.
3. Create **one** test student or lecturer with an address you can check
   (your own inbox).
4. On success the results screen shows *"Login details emailed to 1 of 1"* (or
   the credentials card notes the email was sent).
5. Check the inbox (and spam folder). The message is from `SYSTEM_EMAIL_NAME`
   and contains the login link + credentials.

If nothing arrives, check the PHP error log for lines beginning `[CES][mail]` —
PHPMailer records the SMTP conversation error there.

---

## 7. Troubleshooting

| Symptom | Likely cause / fix |
|---------|--------------------|
| "emailed 0 of N", log shows `SMTP connect() failed` | Wrong `SMTP_HOST`/`SMTP_PORT`, or the host firewall blocks outbound SMTP. Confirm the port with your host. |
| Log shows `SMTP Error: Could not authenticate` | Wrong `SMTP_USER`/`SMTP_PASS`; for Gmail use an App Password. |
| Log shows a TLS/certificate error | Try `SMTP_SECURE=ssl` with port `465`, or `tls` with `587`. |
| Emails send but the login link points at `localhost` | Set `APP_URL` to the real site URL. |
| No email at all, no SMTP set | You're on PHP `mail()`; many hosts disable it — configure SMTP instead. |
| Mail lands in spam | Set up SPF/DKIM for your domain and use a `SMTP_FROM` on that domain. |

---

## 8. Extending — adding a new email

Reuse the shared helpers; don't call PHPMailer directly from feature code.

```php
require_once __DIR__ . '/../includes/mailer.php';

$subject = 'Evaluation period is now open';
$body    = "Hello,\n\nThe evaluation window is open until Friday.\n"
         . ces_absolute_base_url() . "/login.php\n\n— " . INSTITUTION_NAME;

// $to_name is used as the recipient display name
$sent = ces_deliver_mail($to_email, $to_name, $subject, $body);
```

- `ces_send_login_details(...)` — the ready-made "welcome / account created" email.
- `ces_deliver_mail($to, $to_name, $subject, $body)` — generic plain-text send.
- `ces_absolute_base_url()` — absolute site URL for links in any email.

Keep bodies plain text unless you also set `$mail->isHTML(true)` inside a new
helper. Send bulk/notification email **after** any database transaction commits
(as the imports do) so slow mail never holds a lock or rolls back data.

---

## 9. Files involved

| File | Role |
|------|------|
| `includes/mailer.php` | All mail logic (SMTP/mail fallback, URL builder) |
| `config/constants.php` | `SYSTEM_EMAIL_*`, `APP_NAME`, `INSTITUTION_NAME`, `APP_URL` |
| `vendor/phpmailer/…` | PHPMailer library (committed) |
| `composer.json` / `composer.lock` | Dependency manifest (PHPMailer `^6.9`, platform PHP 7.2) |
