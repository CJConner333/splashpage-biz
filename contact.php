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

$smtpHost = getenv('SMTP_HOST') ?: 'smtp.ipage.com';
$smtpPort = filter_var(getenv('SMTP_PORT') ?: '465', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);
$smtpUsername = getenv('SMTP_USERNAME');
$smtpPassword = getenv('SMTP_PASSWORD');
$smtpEncryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'ssl');
$fromEmail = getenv('SMTP_FROM_EMAIL') ?: $smtpUsername;

if (
    $smtpPort === false ||
    $smtpUsername === false || $smtpUsername === '' ||
    $smtpPassword === false || $smtpPassword === '' ||
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
