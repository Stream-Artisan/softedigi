<?php
session_start();

// CSRF Protection
function generateToken() {
    return bin2hex(random_bytes(32));
}

function validateToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate Limiting
function checkRateLimit() {
    $timeFrame = 3600; // 1 hour
    $maxAttempts = 5;
    
    if (!isset($_SESSION['submission_attempts'])) {
        $_SESSION['submission_attempts'] = array();
    }
    
    // Clean old attempts
    $_SESSION['submission_attempts'] = array_filter($_SESSION['submission_attempts'], function($timestamp) use ($timeFrame) {
        return $timestamp > time() - $timeFrame;
    });
    
    if (count($_SESSION['submission_attempts']) >= $maxAttempts) {
        return false;
    }
    
    $_SESSION['submission_attempts'][] = time();
    return true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateToken($_POST['csrf_token'])) {
        header("Location: contact.html?status=invalid_token");
        exit();
    }
    
    // Check rate limit
    if (!checkRateLimit()) {
        header("Location: contact.html?status=rate_limit");
        exit();
    }

    // Define recipient email
    $to = "info@softedigi.com";
    $subject = "New Contact Form Submission";

    // Initialize variables
    $name = $email = $number = $service = $subject_field = $message = "";
    $errors = [];

    // Sanitize and validate inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

    // Check for service (first form) or subject (second form)
    if (isset($_POST['service'])) {
        $service = filter_input(INPUT_POST, 'service', FILTER_SANITIZE_STRING);
    }
    if (isset($_POST['subject'])) {
        $subject_field = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    }

    // Enhanced validation
    if (empty($name) || strlen($name) > 100) {
        $errors[] = "Name is required and must be less than 100 characters.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
        $errors[] = "A valid email is required.";
    }
    if (empty($number) || !preg_match("/^[0-9+\-\s()]{6,20}$/", $number)) {
        $errors[] = "A valid phone number is required.";
    }
    if (empty($message) || strlen($message) > 3000) {
        $errors[] = "Message is required and must be less than 3000 characters.";
    }

    // If no errors, proceed to send email
    if (empty($errors)) {
        try {
            // Build email content
            $email_content = "New Contact Form Submission\n\n";
            $email_content .= "Name: " . htmlspecialchars($name) . "\n";
            $email_content .= "Email: " . htmlspecialchars($email) . "\n";
            $email_content .= "Phone Number: " . htmlspecialchars($number) . "\n";
            if (!empty($service)) {
                $email_content .= "Service: " . htmlspecialchars($service) . "\n";
            }
            if (!empty($subject_field)) {
                $email_content .= "Subject: " . htmlspecialchars($subject_field) . "\n";
            }
            $email_content .= "Message:\n" . htmlspecialchars($message) . "\n";

            // Set secure headers
            $headers = array(
                'From: no-reply@softedigi.com',
                'Reply-To: ' . $email,
                'X-Mailer: PHP/' . phpversion(),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'X-Content-Type-Options: nosniff',
                'X-Frame-Options: DENY',
                'X-XSS-Protection: 1; mode=block'
            );

            // Attempt to send email
            if (mail($to, $subject, $email_content, implode("\r\n", $headers))) {
                // Log successful submission
                error_log("Form submission successful from: " . $email);
                
                // Generate new CSRF token for next submission
                $_SESSION['csrf_token'] = generateToken();
                
                header("Location: contact.html?status=success");
                exit();
            } else {
                throw new Exception("Failed to send email");
            }
        } catch (Exception $e) {
            error_log("Form submission error: " . $e->getMessage());
            header("Location: contact.html?status=error");
            exit();
        }
    } else {
        $_SESSION['form_errors'] = $errors;
        header("Location: contact.html?status=validation_error");
        exit();
    }
} else {
    // Generate new CSRF token for the form
    $_SESSION['csrf_token'] = generateToken();
    header("Location: contact.html");
    exit();
}
?>