<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Log channel
    |--------------------------------------------------------------------------
    | Channel defined in config/logging.php that receives security events.
    | See README: it writes JSON to storage/logs/security-*.log and to the SIEM.
    */
    'log_channel' => env('SECURITY_LOG_CHANNEL', 'security'),

    /*
    |--------------------------------------------------------------------------
    | Sensitive fields
    |--------------------------------------------------------------------------
    | Never inspected, never logged (values are replaced by [REDACTED]).
    | Matching is by field name, case-insensitive. Add project-specific names.
    */
    'sensitive_fields' => [
        'password', 'password_confirmation', 'current_password', 'new_password',
        '_token', 'token', 'access_token', 'refresh_token', 'api_key', 'secret',
        'otp', 'two_factor_code', 'recovery_code',
        'card_number', 'cvv', 'iban', 'tckn', 'tc_kimlik_no', 'tc_no', 'identity_no',
        'avans_hesap_no',
    ],

    'logging' => [
        'application' => env('SECURITY_APP_NAME', env('APP_NAME', 'koksan-portal')),
        // Max characters kept from any logged string value.
        'max_value_length' => 200,
        // true = usernames in logs become HMAC pseudonyms (still correlatable, not readable).
        'pseudonymize_usernames' => (bool) env('SECURITY_PSEUDONYMIZE_USERNAMES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Brute force protection
    |--------------------------------------------------------------------------
    */
    'login_throttle' => [
        // Request field holding the username in login forms / credential arrays.
        'username_field' => env('SECURITY_LOGIN_USERNAME_FIELD', 'email'),
        'max_account_attempts' => (int) env('SECURITY_LOGIN_MAX_ACCOUNT_ATTEMPTS', 5),
        'max_ip_attempts' => (int) env('SECURITY_LOGIN_MAX_IP_ATTEMPTS', 20),
        'decay_seconds' => (int) env('SECURITY_LOGIN_DECAY_SECONDS', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | SQLi / XSS signature inspection
    |--------------------------------------------------------------------------
    | mode: "monitor" logs only (recommended at first), "block" also returns 403.
    | Blocking belongs in a WAF; enable "block" only after reviewing false positives.
    */
    'inspection' => [
        'enabled' => (bool) env('SECURITY_INSPECTION_ENABLED', true),
        'mode' => env('SECURITY_INSPECTION_MODE', 'monitor'),
        // Fields that legitimately carry HTML/SQL-like text (rich text editors, code snippets).
        'excluded_fields' => [
            'body_content', 'template_content', 'description_html',
        ],
        // Request::is() patterns skipped entirely, e.g. 'admin/cms/*'.
        'excluded_paths' => [
            'dev/*',
        ],
        // Only the first N bytes of each value are inspected.
        'max_inspect_length' => 8192,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scanning detection by error rate
    |--------------------------------------------------------------------------
    | One attack.scanning_behavior event when a single IP produces `threshold`
    | error responses within `window_seconds`.
    */
    'error_rate' => [
        'threshold' => (int) env('SECURITY_ERROR_RATE_THRESHOLD', 50),
        'window_seconds' => (int) env('SECURITY_ERROR_RATE_WINDOW', 60),
        'counted_statuses' => [400, 401, 403, 404, 405, 419, 422, 429],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy with violation reporting
    |--------------------------------------------------------------------------
    | Start with report_only=true; the policy must match the assets the app really
    | uses (CDNs, analytics, inline scripts) before switching to enforcing.
    */
    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', true),
        'policy' => env('SECURITY_CSP_POLICY', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' fonts.bunny.net fonts.googleapis.com; font-src 'self' data: fonts.bunny.net fonts.gstatic.com; img-src 'self' data: blob: https:; connect-src 'self' ws: wss:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'"),
        'report_path' => 'security/csp-report',
        // Requests per minute per IP accepted on the report endpoint.
        'report_rate_limit' => 60,
    ],

];
