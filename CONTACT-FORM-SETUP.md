# Contact form email setup

The contact form uses the bundled PHPMailer source in `PHPMailer-7.1.1/` and sends through authenticated SMTP. Keep that directory alongside `contact.php` when deploying.

Store the SMTP login in a PHP configuration file outside `public_html`. For this deployment layout, place the configuration file here:

```text
account-root/
├── private/
│   └── smtp-config-splashpage.php
└── public_html/
    └── splashpage-biz/
        ├── contact.php
        └── PHPMailer-7.1.1/
```

In this layout, `contact.php` loads `../../private/smtp-config-splashpage.php`, which resolves to `account-root/private/smtp-config-splashpage.php`. If you deploy `contact.php` in a different directory depth, update that relative path in `contact.php` accordingly.

Create `private/smtp-config-splashpage.php` with the following contents:

```php
<?php
return [
    'host' => 'smtp.ipage.com',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => 'your-full-email-address@splashpage.biz',
    'password' => 'your-mailbox-password',
    'from_email' => 'your-full-email-address@splashpage.biz',
    'turnstile_secret' => 'your-turnstile-secret-key',
];
```

`from_email` must be a sender address authorized by the SMTP provider; it can be omitted to use the SMTP username. Keep the real credentials out of source control and restrict access to this file using your host's file permissions.

This Markdown file is documentation only; PHP/PHPMailer does not load it. The PHP configuration file containing the SMTP credentials is required at runtime.

The endpoint sends messages to `support@splashpage.biz` and sets the submitter's address as the reply-to address. The configured SMTP account must be permitted to send from `from_email`.

## Cloudflare Turnstile

The contact form also requires a Cloudflare Turnstile verification. Create a Turnstile widget for the domains where this site is hosted.

The Turnstile site key is configured in the `data-sitekey` attribute of the widget in `index.html`; the provided site key is already in place. Add the matching Turnstile secret as `turnstile_secret` in the same private SMTP configuration file. Keep the secret private and out of source control. `contact.php` verifies every submitted token with Cloudflare's Siteverify API before sending email; the server must have PHP's cURL extension enabled. The site key and secret must belong to the same Turnstile widget.

The browser loads `contact.php` only when the visitor submits the form; it is not called when `index.html` loads. `script.js` sends the submission as a same-origin request and keeps the visitor on the page. It also ignores duplicate submissions and treats redirects as errors rather than following a host challenge page. Do not preload `contact.php` or use a rewrite to bypass a hosting-provider bot challenge.

If submitting the form navigates to `contact.php` or displays a host challenge, check the browser console and Network panel: a normal submission should be a single POST to `contact.php` from the form submit. If JavaScript did not handle it, the browser's native form fallback will navigate to the endpoint; if the request was handled by JavaScript, check its HTTP status and response for a host/WAF block or redirect. Ask the hosting provider to review the matching access/WAF log entry and allow the legitimate same-origin form POST while keeping the server-side Turnstile verification enabled.
