<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    // Validate required fields
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if (empty($number)) {
        $errors[] = "Phone number is required.";
    }
    if (empty($message)) {
        $errors[] = "Message is required.";
    }

    // If no errors, proceed to send email
    if (empty($errors)) {
        // Build email content
        $email_content = "New Contact Form Submission\n\n";
        $email_content .= "Name: $name\n";
        $email_content .= "Email: $email\n";
        $email_content .= "Phone Number: $number\n";
        if (!empty($service)) {
            $email_content .= "Service: $service\n";
        }
        if (!empty($subject_field)) {
            $email_content .= "Subject: $subject_field\n";
        }
        $email_content .= "Message:\n$message\n";

        // Set headers to prevent injection and specify sender
        $headers = "From: no-reply@softedigi.com\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Attempt to send email
        if (mail($to, $subject, $email_content, $headers)) {
            // Redirect to a success page with a query parameter
            header("Location: contact.html?status=success");
            exit();
        } else {
            // Redirect to contact page with error
            header("Location: contact.html?status=error");
            exit();
        }
    } else {
        // Store errors in session and redirect
        session_start();
        $_SESSION['form_errors'] = $errors;
        header("Location: contact.html?status=validation_error");
        exit();
    }
} else {
    // Redirect if accessed directly
    header("Location: contact.html");
    exit();
}
?>