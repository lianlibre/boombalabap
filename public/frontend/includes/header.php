<?php
// === SECURITY HEADERS ===
// Prevent XSS, clickjacking, MIME sniffing

// Content Security Policy (CSP)
// Allows only trusted sources (your domain, Google Fonts, CDN JS)
header("Content-Security-Policy: 
    default-src 'self';
    script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;
    style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;
    img-src 'self' data: https:;
    font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com;
    connect-src 'self';
    frame-ancestors 'self';
    base-uri 'self';
    form-action 'self';
");

// HTTP Strict Transport Security (HSTS)
// Forces browser to use HTTPS for 1 year
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Additional hardening headers
header("X-Content-Type-Options: nosniff");           // Stop MIME type sniffing
header("X-Frame-Options: DENY");                    // Prevent clickjacking
header("X-XSS-Protection: 1; mode=block");          // Enable XSS filter (legacy)
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=()");