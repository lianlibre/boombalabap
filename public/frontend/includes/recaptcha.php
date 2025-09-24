<?php
// === includes/recaptcha.php ===
// Global reCAPTCHA v3 Helper Functions

// 🔐 Set your secret key here (keep secure!)
define('RECAPTCHA_SECRET_KEY', '6Leg8NIrAAAAAPzNl2xE9SogG7H_VkY99LN5qaHo');

// 🌐 Site key for frontend
define('RECAPTCHA_SITE_KEY', '6Leg8NIrAAAAADfLDDQzC2kgdY0t8i3KWIb-z6aS');

/**
 * Verify reCAPTCHA token with Google
 * @param string $token - The g-recaptcha-response from frontend
 * @return array - ['success' => bool, 'score' => float|null, 'error' => string]
 */
function verifyRecaptcha($token) {
    if (empty($token)) {
        return [
            'success' => false,
            'score' => null,
            'error' => 'No reCAPTCHA token provided.'
        ];
    }

    // 🔗 Correct URL (no extra spaces!)
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    
    $data = http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => $data
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) {
        return [
            'success' => false,
            'score' => null,
            'error' => 'Failed to connect to reCAPTCHA service.'
        ];
    }

    $result = json_decode($response, true);

    // Optional: Enforce minimum score (e.g., 0.5)
    $score = $result['score'] ?? null;
    $requiredScore = 0.5;
    if (isset($score) && $score < $requiredScore) {
        return [
            'success' => false,
            'score'   => $score,
            'action'  => $result['action'] ?? '',
            'error'   => 'reCAPTCHA score too low (' . $score . ')'
        ];
    }

    return [
        'success' => $result['success'] ?? false,
        'score'   => $score,
        'action'  => $result['action'] ?? '',
        'error'   => !empty($result['error-codes']) ? implode(', ', $result['error-codes']) : ''
    ];
}

/**
 * Echo the reCAPTCHA v3 script and auto-execute on form submit
 * @param string $action - Action name (e.g., 'login', 'register')
 */
function renderRecaptchaScript($action = 'submit') {
    $siteKey = RECAPTCHA_SITE_KEY;

    echo <<<HTML
<script src="https://www.google.com/recaptcha/api.js?render={$siteKey}"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof grecaptcha !== 'undefined') {
        // Attach to all forms that have data-recaptcha attribute
        document.querySelectorAll('form[data-recaptcha]').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault(); // Prevent immediate submit

                grecaptcha.ready(function () {
                    grecaptcha.execute('{$siteKey}', { action: '{$action}' }).then(function (token) {
                        // Add or update hidden input with token
                        let recaptchaInput = form.querySelector('input[name="g-recaptcha-response"]');
                        if (!recaptchaInput) {
                            recaptchaInput = document.createElement('input');
                            recaptchaInput.type = 'hidden';
                            recaptchaInput.name = 'g-recaptcha-response';
                            form.appendChild(recaptchaInput);
                        }
                        recaptchaInput.value = token;

                        // Now submit the form
                        form.submit();
                    });
                });
            });
        });
    } else {
        console.warn("reCAPTCHA failed to load. Check internet or ad blockers.");
    }
});
</script>
HTML;
}