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

foreach (['name', 'email', 'subject', 'message', 'company', 'cf-turnstile-response'] as $field) {
    if (isset($_POST[$field]) && !is_string($_POST[$field])) {
        respond(400, ['error' => 'Please check the form fields and try again.']);
    }
}

$turnstileToken = $_POST['cf-turnstile-response'] ?? '';
if (!is_string($turnstileToken) || $turnstileToken === '' || strlen($turnstileToken) > 2048) {
    respond(400, ['error' => 'Please complete the security check and try again.']);
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

$turnstileConfigPath = dirname(__DIR__, 2) . '/private/turnstile-splashpage-config.php';
if (!is_file($turnstileConfigPath)) {
    error_log('Contact form Turnstile configuration file is missing.');
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

try {
    $turnstileConfig = require $turnstileConfigPath;
} catch (\ParseError $error) {
    error_log('Contact form Turnstile configuration has a PHP syntax error: ' . $error->getMessage());
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

if (!is_array($turnstileConfig)) {
    error_log('Contact form Turnstile configuration is invalid.');
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

$turnstileSecret = $turnstileConfig['turnstile_secret'] ?? null;
$configuredHostnames = $turnstileConfig['turnstile_hostnames'] ?? null;
if (
    !is_string($turnstileSecret) || $turnstileSecret === '' ||
    !is_array($configuredHostnames) || $configuredHostnames === []
) {
    error_log('Contact form Turnstile configuration is missing or invalid.');
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

$turnstileHostnames = [];
foreach ($configuredHostnames as $hostname) {
    if (!is_string($hostname) || trim($hostname) === '') {
        error_log('Contact form Turnstile hostname configuration is invalid.');
        respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
    }
    $turnstileHostnames[] = strtolower(trim($hostname));
}

if (!function_exists('curl_init')) {
    error_log('Contact form Turnstile verification requires the PHP cURL extension.');
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

$verificationFields = [
    'secret' => $turnstileSecret,
    'response' => $turnstileToken,
];
if (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
    $remoteIp = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
    if ($remoteIp !== false) {
        $verificationFields['remoteip'] = $remoteIp;
    }
}

$curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
if ($curl === false) {
    error_log('Could not initialize the Turnstile verification request.');
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($verificationFields),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$verificationBody = curl_exec($curl);
$verificationStatus = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$verificationError = curl_error($curl);
curl_close($curl);

if ($verificationBody === false || $verificationStatus < 200 || $verificationStatus >= 300) {
    error_log('Turnstile verification request failed: ' . $verificationError);
    respond(502, ['error' => 'The security check could not be verified. Please try again later.']);
}

$verificationResult = json_decode($verificationBody, true);
if (
    !is_array($verificationResult) ||
    ($verificationResult['success'] ?? false) !== true ||
    ($verificationResult['action'] ?? null) !== 'contact' ||
    !is_string($verificationResult['hostname'] ?? null) ||
    !in_array(strtolower($verificationResult['hostname']), $turnstileHostnames, true)
) {
    error_log('Turnstile returned an invalid verification response.');
    respond(403, ['error' => 'Please complete the security check and try again.']);
}

if (!function_exists('mail')) {
    error_log('Contact form email requires PHP mail().');
    respond(500, ['error' => 'The contact form is not configured correctly. Please try again later.']);
}

$mailBody = implode("\r\n", [
    'Name: ' . $name,
    'Email: ' . $email,
    'Subject: ' . $subject,
    '',
    'Message:',
    $message,
]);
$subjectCharacters = preg_split('//u', 'Website contact: ' . $subject, -1, PREG_SPLIT_NO_EMPTY);
if ($subjectCharacters === false) {
    respond(400, ['error' => 'Please check the form fields and try again.']);
}

$mailSubjectParts = [];
$subjectPart = '';
foreach ($subjectCharacters as $character) {
    if ($subjectPart !== '' && strlen($subjectPart) + strlen($character) > 42) {
        $mailSubjectParts[] = '=?UTF-8?B?' . base64_encode($subjectPart) . '?=';
        $subjectPart = '';
    }
    $subjectPart .= $character;
}
if ($subjectPart !== '') {
    $mailSubjectParts[] = '=?UTF-8?B?' . base64_encode($subjectPart) . '?=';
}
$mailSubject = implode(' ', $mailSubjectParts);
$mailHeaders = implode("\r\n", [
    'From: support@splashpage.biz',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
]);

$mailError = null;
$mailSent = false;
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
try {
    $mailSent = mail('support@splashpage.biz', $mailSubject, $mailBody, $mailHeaders);
} catch (\Throwable $error) {
    $mailError = $error;
} finally {
    restore_error_handler();
}

if ($mailError !== null) {
    error_log('Contact form mail() error: ' . $mailError->getMessage());
    respond(500, ['error' => 'We could not send your message right now. Please try again later.']);
}
if (!$mailSent) {
    error_log('Contact form mail() returned false.');
    respond(500, ['error' => 'We could not send your message right now. Please try again later.']);
}

respond(200, ['message' => 'Message sent.']);
