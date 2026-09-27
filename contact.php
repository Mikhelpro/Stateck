<?php
/**
 * Self-hosted contact form handler for Stateck Labs.
 * No third-party service (e.g. formsubmit.co) is used - this sends mail
 * directly from your own hosting using PHP's built-in mail() function.
 *
 * Requirements:
 *  - Your hosting must support PHP and have mail() enabled (almost all
 *    cPanel / shared hosting for a "public_html" site does by default).
 *  - For best deliverability, set up SPF/DKIM for your domain with your
 *    host, and send FROM an address on your own domain (see $from below).
 */

// ---- Settings ----
$recipient   = "Statecklabs@gmail.com"; // where messages are delivered
$site_name   = "Stateck Labs";
$redirect_ok = "thank-you.html";              // page to show after success

// ---- Only accept POST requests ----
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die("Method not allowed.");
}

// ---- Honeypot spam trap (hidden field, real users leave it empty) ----
if (!empty($_POST["_honey"])) {
    // Silently pretend success to bots
    header("Location: " . $redirect_ok);
    exit;
}

// ---- Collect + sanitize input ----
function clean($value) {
    $value = trim($value ?? "");
    $value = str_replace(["\r", "\n"], "", $value); // block header injection
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

$name    = clean($_POST["name"] ?? "");
$email   = trim($_POST["email"] ?? "");
$company = clean($_POST["company"] ?? "");
$message = trim($_POST["message"] ?? "");

// ---- Basic validation ----
$errors = [];
if ($name === "") $errors[] = "Name is required.";
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
if ($message === "") $errors[] = "Message is required.";

if (!empty($errors)) {
    http_response_code(422);
    echo "<h2>There was a problem with your submission:</h2><ul>";
    foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul><p><a href='javascript:history.back()'>Go back</a></p>";
    exit;
}

$message_clean = htmlspecialchars($message, ENT_QUOTES, "UTF-8");

// ---- Build the email ----
$subject = "New Contact from $site_name" . ($company !== "" ? " ($company)" : "");

$body  = "You received a new message from your website contact form:\n\n";
$body .= "Name: $name\n";
$body .= "Email: $email\n";
if ($company !== "") $body .= "Company: $company\n";
$body .= "\nMessage:\n$message_clean\n";

// IMPORTANT: use an address on YOUR domain for best deliverability.
// From address on your own domain (statecklabs.pro.et) for best deliverability.
$from_domain = "noreply@" . preg_replace('/^www\./', '', $_SERVER["HTTP_HOST"] ?? "statecklabs.pro.et");

$headers  = "From: $site_name <$from_domain>\r\n";
$headers .= "Reply-To: $name <$email>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// ---- Send ----
$sent = mail($recipient, $subject, $body, $headers);

if ($sent) {
    header("Location: " . $redirect_ok);
    exit;
} else {
    http_response_code(500);
    echo "<h2>Sorry, something went wrong sending your message.</h2>";
    echo "<p>Please email us directly at <a href='mailto:$recipient'>$recipient</a> instead.</p>";
    echo "<p><a href='javascript:history.back()'>Go back</a></p>";
    exit;
}
