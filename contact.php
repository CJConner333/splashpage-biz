<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$phpMailerPath = __DIR__ . '/PHPMailer-7.1.1/src/';
foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $phpMailerFile) {
    if (!is_file($phpMailerPath . $phpMailerFile)) {
        error_log('Contact form email dependencies are missing.');
        respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
    }
    require_once $phpMailerPath . $phpMailerFile;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Method not allowed.']);
}

foreach (['name', 'email', 'subject', 'message', 'company'] as $field) {
    if (isset($_POST[$field]) && !is_string($_POST[$field])) {
        respond(400, ['error' => 'Please check the form fields and try again.']);
    }
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$company = trim($_POST['company'] ?? '');

if ($company !== '') {
    respond(400, ['error' => 'Unable to submit the form.']);
}

if (
    $name === '' || strlen($name) > 150 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 ||
    $subject === '' || strlen($subject) > 200 ||
    $message === '' || strlen($message) > 5000 ||
    preg_match('/[\r\n]/', $email) ||
    preg_match('/[\r\n]/', $name) ||
    preg_match('/[\r\n]/', $subject)
) {
    respond(400, ['error' => 'Please check the form fields and try again.']);
}

$smtpConfigPath = __DIR__ . '/../../private/smtp-config-splashpage.php';
if (!is_file($smtpConfigPath)) {
    error_log('Contact form SMTP configuration file is missing.');
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

$smtpConfig = require $smtpConfigPath;
if (
    !is_array($smtpConfig) ||
    !isset(
        $smtpConfig['host'],
        $smtpConfig['port'],
        $smtpConfig['encryption'],
        $smtpConfig['username'],
        $smtpConfig['password']
    ) ||
    !is_string($smtpConfig['host']) ||
    !is_int($smtpConfig['port']) ||
    !is_string($smtpConfig['encryption']) ||
    !is_string($smtpConfig['username']) ||
    !is_string($smtpConfig['password']) ||
    (isset($smtpConfig['from_email']) && !is_string($smtpConfig['from_email'])) ||
    $smtpConfig['host'] === '' ||
    $smtpConfig['port'] < 1 ||
    $smtpConfig['port'] > 65535 ||
    $smtpConfig['username'] === '' ||
    $smtpConfig['password'] === ''
) {
    error_log('Contact form SMTP configuration file is invalid.');
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

$smtpHost = $smtpConfig['host'];
$smtpPort = $smtpConfig['port'];
$smtpUsername = $smtpConfig['username'];
$smtpPassword = $smtpConfig['password'];
$smtpEncryption = strtolower($smtpConfig['encryption']);
$fromEmail = $smtpConfig['from_email'] ?? $smtpUsername;

if (
    !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ||
    !in_array($smtpEncryption, ['tls', 'ssl'], true)
) {
    error_log('Contact form SMTP settings are missing or invalid.');
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

$mailBody = implode("\n", [
    'Name: ' . $name,
    'Email: ' . $email,
    'Subject: ' . $subject,
    '',
    'Message:',
    $message,
]);
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->Port = $smtpPort;
    $mail->SMTPSecure = $smtpEncryption === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Timeout = 15;
    $mail->setFrom($fromEmail, 'Splash Page Website');
    $mail->addAddress('support@splashpage.biz');
    $mail->addReplyTo($email, $name);
    $mail->Subject = 'Website contact: ' . $subject;
    $mail->Body = $mailBody;
    $mail->send();
} catch (\Throwable $error) {
    error_log('Contact form SMTP error: ' . $error->getMessage());
    respond(500, ['error' => 'We could not send your message right now. Please try again later.']);
}

respond(200, ['message' => 'Message sent.']);
