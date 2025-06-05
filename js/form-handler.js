document.addEventListener('DOMContentLoaded', function() {
    // Get CSRF token when page loads
    fetch('send_email.php')
        .then(response => {
            // The PHP script will set the token in the URL when redirecting
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('csrf_token');
            if (token) {
                document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                    input.value = token;
                });
            }
        })
        .catch(error => console.error('Error fetching CSRF token:', error));

    // Form submission handler
    document.querySelectorAll('form[action="send_email.php"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            // Get the full international number from the intl-tel-input instance
            const phoneInput = form.querySelector('input[name="number"]');
            if (phoneInput && phoneInput.iti) {
                formData.set('number', phoneInput.iti.getNumber());
            }

            // Clear previous messages
            const messagesDiv = form.querySelector('#form-messages');
            if (messagesDiv) {
                messagesDiv.innerHTML = '';
                messagesDiv.className = '';
            }

            // Submit form
            fetch('send_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(response => {
                const urlParams = new URLSearchParams(response);
                const status = urlParams.get('status');
                
                if (messagesDiv) {
                    let message = '';
                    let className = '';
                    
                    switch(status) {
                        case 'success':
                            message = 'Thank you! Your message has been sent.';
                            className = 'alert alert-success';
                            form.reset();
                            break;
                        case 'invalid_token':
                            message = 'Security validation failed. Please refresh the page and try again.';
                            className = 'alert alert-danger';
                            break;
                        case 'rate_limit':
                            message = 'Too many attempts. Please try again later.';
                            className = 'alert alert-warning';
                            break;
                        case 'validation_error':
                            message = 'Please check your inputs and try again.';
                            className = 'alert alert-danger';
                            break;
                        default:
                            message = 'An error occurred. Please try again later.';
                            className = 'alert alert-danger';
                    }
                    
                    messagesDiv.innerHTML = `<div class="${className}">${message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (messagesDiv) {
                    messagesDiv.innerHTML = '<div class="alert alert-danger">An error occurred. Please try again later.</div>';
                }
            });
        });
    });

    // Phone number formatting
    document.querySelectorAll('input[name="number"]').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d\s+()-]/g, '');
            if (value.length > 20) {
                value = value.substring(0, 20);
            }
            e.target.value = value;
        });
    });
}); 