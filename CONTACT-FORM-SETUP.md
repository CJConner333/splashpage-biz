# Contact form email setup

The contact form uses the bundled PHPMailer source in `PHPMailer-7.1.1/` and sends through authenticated SMTP. Keep that directory alongside `contact.php` when deploying.

Configure these environment variables in the PHP hosting environment; do not put SMTP credentials in source control:

| Variable | Required | Default / expected value |
| --- | --- | --- |
| `SMTP_HOST` | No | `smtp.ipage.com` |
| `SMTP_PORT` | No | `465` |
| `SMTP_ENCRYPTION` | No | `ssl`; use `tls` for STARTTLS |
| `SMTP_USERNAME` | Yes | SMTP account username |
| `SMTP_PASSWORD` | Yes | SMTP account password |
| `SMTP_FROM_EMAIL` | No | `SMTP_USERNAME`; use a sender address authorized by the SMTP provider |

The endpoint sends messages to `support@splashpage.biz` and sets the submitter's address as the reply-to address. The configured SMTP account must be permitted to send from `SMTP_FROM_EMAIL`.
