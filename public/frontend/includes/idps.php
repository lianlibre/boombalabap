<?php
// === BASIC INTRUSION DETECTION & PREVENTION SYSTEM (IDPS) ===
// Blocks known attack patterns and logs suspicious activity

function logSuspiciousActivity($type, $details = []) {
    $logFile = __DIR__ . '/../logs/suspicious.log';
    $entry = sprintf(
        "[%s] TYPE='%s' IP='%s' METHOD='%s' URI='%s' DATA='%s'\n",
        date('c'),
        $type,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['REQUEST_METHOD'],
        $_SERVER['REQUEST_URI'],
        json_encode($details)
    );
    error_log($entry, 3, $logFile);
}

function blockRequest($reason) {
    http_response_code(403);
    die("<h1>Access Denied</h1><p>Reason: $reason</p><small>IDPS Triggered</small>");
}

function runIDPS() {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // === 1. Block known malicious User-Agents ===
    $badUAs = ['sqlmap', 'nikto', 'wget', 'curl', 'python-requests'];
    foreach ($badUAs as $ua) {
        if (stripos($userAgent, $ua) !== false) {
            logSuspiciousActivity('BAD_USER_AGENT', ['user_agent' => $userAgent]);
            blockRequest("Invalid user agent detected.");
        }
    }

    // === 2. Scan GET/POST for SQL Injection ===
    $input = array_merge($_GET, $_POST);
    $sqliPatterns = [
        '/\b(and|or)\s*1=1/i',
        '/\bunion\s+select/i',
        '/\bsleep\s*\(\d+\)/i',
        '/\bwaitfor\s+delay/i',
        '/\'\s*--/',
        '/;\s*exec\s+/i'
    ];

    foreach ($input as $key => $value) {
        foreach ($sqliPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                logSuspiciousActivity('SQLI_ATTEMPT', ['field' => $key, 'value' => $value, 'pattern' => $pattern]);
                blockRequest("SQL injection attempt detected.");
            }
        }
    }

    // === 3. Scan for XSS Attempts ===
    $xssPatterns = [
        '/<script[^>]*>/i',
        '/javascript:/i',
        '/onload=/i',
        '/onerror=/i',
        '/<iframe/i',
        '/<img.*src=.*javascript:/i'
    ];

    foreach ($input as $key => $value) {
        foreach ($xssPatterns as $pattern) {
            if (is_string($value) && preg_match($pattern, $value)) {
                logSuspiciousActivity('XSS_ATTEMPT', ['field' => $key, 'value' => $value]);
                blockRequest("Cross-site scripting (XSS) attempt blocked.");
            }
        }
    }

    // === 4. Brute Force Protection (Login Endpoint Only) ===
    if (strpos($_SERVER['REQUEST_URI'], 'login.php') !== false && $method === 'POST') {
        $attemptsFile = __DIR__ . '/../logs/bruteforce_attempts.log';
        $attempts = [];

        // Load recent attempts
        if (file_exists($attemptsFile)) {
            $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
        }

        $now = time();
        $attempts = array_filter($attempts, fn($t) => ($now - $t['time']) < 900); // Keep last 15 mins

        // Count attempts from this IP
        $recentFromIP = array_filter($attempts, fn($t) => $t['ip'] === $clientIP);
        if (count($recentFromIP) >= 5) {
            logSuspiciousActivity('BRUTE_FORCE', ['ip' => $clientIP, 'attempts' => count($recentFromIP)]);
            blockRequest("Too many login attempts. Try again later.");
        }

        // Log this attempt
        $attempts[] = ['ip' => $clientIP, 'time' => $now];
        file_put_contents($attemptsFile, json_encode($attempts));
    }

    // === 5. Block Direct Access to Sensitive Files ===
    $blockedFiles = ['/config.php', '/db.php', '/.env'];
    foreach ($blockedFiles as $file) {
        if (strpos($_SERVER['REQUEST_URI'], $file) !== false) {
            logSuspiciousActivity('SENSITIVE_FILE_ACCESS', ['uri' => $_SERVER['REQUEST_URI']]);
            blockRequest("Access to sensitive file denied.");
        }
    }
}

// Run IDPS on every request
runIDPS();