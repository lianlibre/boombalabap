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

    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) {
        return [
            'success' => false,
            'score' => null,
            'error' => 'Failed to connect to reCAPTCHA service.'
        ];
    }

    $result = json_decode($response, true);

    return [
        'success' => $result['success'] ?? false,
        'score'   => $result['score'] ?? null,
        'action'  => $result['action'] ?? '',
        'error'   => !empty($result['error-codes']) ? implode(', ', $result['error-codes']) : ''
    ];
}

/**
 * Echo the reCAPTCHA v3 script and auto-execute on page load
 * @param string $action - Action name (e.g., 'login', 'forgot_password')
 */
function renderRecaptchaScript($action = 'submit') {
    echo <<<HTML
<script src="https://www.google.com/recaptcha/api.js?render="></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof grecaptcha !== 'undefined') {
        const siteKey = 'RECAPTCHA_SITE_KEY';
        // Inject site key dynamically
        document.querySelector('script[src*="recaptcha/api.js"]').src += siteKey;

        // Find all forms and attach reCAPTCHA token before submit
        document.querySelectorAll('form[data-recaptcha]').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                grecaptcha.ready(function () {
                    grecaptcha.execute(siteKey, { action: '$action' }).then(function (token) {
                        // Append token as hidden input
                        let recaptchaInput = form.querySelector('input[name="g-recaptcha-response"]');
                        if (!recaptchaInput) {
                            recaptchaInput = document.createElement('input');
                            recaptchaInput.type = 'hidden';
                            recaptchaInput.name = 'g-recaptcha-response';
                            form.appendChild(recaptchaInput);
                        }
                        recaptchaInput.value = token;
                        form.submit();
                    });
                });
            });
        });
    } else {
        console.warn("reCAPTCHA failed to load.");
    }
});
</script>
HTML;
}