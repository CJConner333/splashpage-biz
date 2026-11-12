# Contact form email setup

The contact form sends email with PHP's built-in `mail()` function. The web host must have its PHP mail transport configured. PHPMailer is not required.

Store the Turnstile settings in a PHP configuration file outside the website folder. For the hosting layout, place it here:

```text
account-root/
├── private/
│   └── turnstile-splashpage-config.php
└── public_html/
    └── splashpage-biz/
        └── contact.php
```

`contact.php` loads `dirname(__DIR__, 2) . '/private/turnstile-splashpage-config.php'`, matching the working Claws deployment path. If your hosting directory layout differs, update that path accordingly.

The file should contain only the Turnstile settings:

```php
<?php
return [
    'turnstile_secret' => 'your-turnstile-secret-key',
    'turnstile_hostnames' => ['splashpage.biz'],
];
```

Keep the real secret out of source control and restrict access to this file using your host's file permissions. Contact emails are sent from `support@splashpage.biz` to `support@splashpage.biz`; customer replies go to the submitter's address.

This Markdown file is documentation only; `contact.php` requires the PHP configuration file at runtime. PHP `mail()` returning success means the message was accepted for delivery, not necessarily delivered to the recipient.

The endpoint sends messages to `support@splashpage.biz` and sets the submitter's address as the reply-to address.

## Cloudflare Turnstile

The contact form also requires a Cloudflare Turnstile verification. Create a Turnstile widget for the domains where this site is hosted.

The Turnstile site key is configured in the `data-sitekey` attribute of the widget in `index.html`; the provided site key is already in place. Add the matching Turnstile secret as `turnstile_secret` and the allowed hostnames as `turnstile_hostnames` in `turnstile-splashpage-config.php`. Keep the secret private and out of source control. The browser submits the `contact` action, and `contact.php` verifies the token, action, and hostname with Cloudflare's Siteverify API before sending email. The server must have PHP's cURL extension enabled. The site key and secret must belong to the same Turnstile widget.

The browser loads `contact.php` only when the visitor submits the form; it is not called when `index.html` loads. `script.js` sends the submission as a same-origin request and keeps the visitor on the page. It also ignores duplicate submissions and treats redirects as errors rather than following a host challenge page. Do not preload `contact.php` or use a rewrite to bypass a hosting-provider bot challenge.

If the form request returns HTTP 409 with a response such as `<script>document.cookie = "humans_...=1"; document.location.reload(true)</script>`, the hosting provider's bot-protection layer is intercepting the POST before `contact.php` can process it. This JavaScript challenge is not a response from PHP or Cloudflare Turnstile, and it cannot complete inside the form's `fetch()` request. Ask the hosting provider to review the matching access/WAF log entry and exempt the legitimate same-origin POST to `/contact.php` from this injected challenge. Keep the server-side Turnstile verification enabled; do not disable bot protection site-wide or bypass Turnstile.
