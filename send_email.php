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

    // Initialize variables
    $admin_email = "info@softedigi.com";
    $errors = [];

    // Sanitize and validate inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    $service = filter_input(INPUT_POST, 'service', FILTER_SANITIZE_STRING);
    $subject_field = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);

    // Validation
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

    if (empty($errors)) {
        try {
            // 1. Send notification to admin
            $admin_subject = "New Contact Form Submission";
            $admin_message = "New Contact Form Submission\n\n";
            $admin_message .= "Name: " . htmlspecialchars($name) . "\n";
            $admin_message .= "Email: " . htmlspecialchars($email) . "\n";
            $admin_message .= "Phone Number: " . htmlspecialchars($number) . "\n";
            if (!empty($service)) {
                $admin_message .= "Service: " . htmlspecialchars($service) . "\n";
            }
            if (!empty($subject_field)) {
                $admin_message .= "Subject: " . htmlspecialchars($subject_field) . "\n";
            }
            $admin_message .= "\nMessage:\n" . htmlspecialchars($message);

            $admin_headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
            $admin_headers .= "Reply-To: " . $email . "\r\n";
            $admin_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $admin_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $admin_headers .= "X-Content-Type-Options: nosniff\r\n";

            // 2. Send confirmation to user
            $user_subject = "Thank you for contacting Softedigi";
            $user_message = "Dear " . htmlspecialchars($name) . ",\n\n";
            $user_message .= "Thank you for contacting Softedigi. We have received your message and will get back to you shortly.\n\n";
            $user_message .= "Your message details:\n";
            $user_message .= "Subject: " . htmlspecialchars(!empty($subject_field) ? $subject_field : (!empty($service) ? $service : "General Inquiry")) . "\n";
            $user_message .= "Message: " . htmlspecialchars($message) . "\n\n";
            $user_message .= "Best regards,\nSoftedigi Team";

            $user_headers = "From: Softedigi <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
            $user_headers .= "Reply-To: " . $admin_email . "\r\n";
            $user_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $user_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $user_headers .= "X-Content-Type-Options: nosniff\r\n";

            // Send both emails
            $admin_mail_sent = mail($admin_email, $admin_subject, $admin_message, $admin_headers);
            $user_mail_sent = mail($email, $user_subject, $user_message, $user_headers);

            if ($admin_mail_sent && $user_mail_sent) {
                // Generate new CSRF token for next submission
                $_SESSION['csrf_token'] = generateToken();
                header("Location: contact.html?status=success");
                exit();
            } else {
                throw new Exception("Failed to send one or more emails");
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