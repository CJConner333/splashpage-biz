<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
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
    preg_match('/[\r\n]/', $email)
) {
    respond(400, ['error' => 'Please check the form fields and try again.']);
}

$recipient = 'support@splashpage.biz';
$mailSubject = '=?UTF-8?B?' . base64_encode('Website contact: ' . $subject) . '?=';
$mailBody = implode("\n", [
    'Name: ' . $name,
    'Email: ' . $email,
    'Subject: ' . $subject,
    '',
    'Message:',
    $message,
]);
$headers = [
    'From' => 'Splash Page Website <support@splashpage.biz>',
    'Reply-To' => $email,
    'Content-Type' => 'text/plain; charset=UTF-8',
    'X-Mailer' => 'PHP/' . PHP_VERSION,
];

if (!mail($recipient, $mailSubject, $mailBody, $headers)) {
    error_log('Contact form email could not be sent.');
    respond(500, ['error' => 'We could not send your message right now. Please try again later.']);
}

respond(200, ['message' => 'Message sent.']);
