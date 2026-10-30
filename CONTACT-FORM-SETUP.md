# Contact form email setup

The contact form uses the bundled PHPMailer source in `PHPMailer-7.1.1/` and sends through authenticated SMTP. Keep that directory alongside `contact.php` when deploying.

Store the SMTP login in a PHP configuration file outside `public_html`. With this project deployed to `public_html/splashpage`, create `private/smtp-config-splashpage.php` alongside `public_html`:

```php
<?php
return [
    'host' => 'smtp.ipage.com',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => 'your-full-email-address@splashpage.biz',
    'password' => 'your-mailbox-password',
    'from_email' => 'your-full-email-address@splashpage.biz',
];
```

`from_email` must be a sender address authorized by the SMTP provider; it can be omitted to use the SMTP username. Keep the real credentials out of source control and restrict access to this file using your host's file permissions. `contact.php` loads it from `../../private/smtp-config-splashpage.php`.

The endpoint sends messages to `support@splashpage.biz` and sets the submitter's address as the reply-to address. The configured SMTP account must be permitted to send from `from_email`.
