<?php

declare(strict_types=1);

namespace App\Security\Enums;

enum SecurityEventType: string
{
    // Authentication
    case LoginSucceeded = 'auth.login_success';
    case LoginFailed = 'auth.login_failed';
    case LoginBlocked = 'auth.login_blocked';
    case AccountLocked = 'auth.account_locked';
    case IpBlocked = 'auth.ip_blocked';
    case FrameworkLockout = 'auth.lockout';
    case PasswordReset = 'auth.password_reset';
    case Logout = 'auth.logout';

    // Authorization / session integrity
    case AccessDenied = 'authz.access_denied';
    case CsrfTokenMismatch = 'session.csrf_mismatch';
    case RateLimited = 'http.rate_limited';

    // Input handling
    case ValidationFailed = 'input.validation_failed';

    // Attack indicators
    case SqlInjectionPattern = 'attack.sqli_pattern';
    case XssPattern = 'attack.xss_pattern';
    case DatabaseSyntaxError = 'attack.db_syntax_error';
    case ScannerUserAgent = 'attack.scanner_user_agent';
    case ScanningBehavior = 'attack.scanning_behavior';
    case CspViolation = 'attack.csp_violation';

    // Application health with security relevance
    case DatabaseError = 'app.database_error';

    public function defaultSeverity(): Severity
    {
        return match ($this) {
            self::LoginSucceeded, self::Logout, self::PasswordReset => Severity::Info,
            self::LoginFailed, self::ValidationFailed => Severity::Low,
            self::AccessDenied, self::CsrfTokenMismatch, self::RateLimited,
            self::ScannerUserAgent, self::CspViolation, self::DatabaseError,
            self::LoginBlocked, self::FrameworkLockout => Severity::Medium,
            self::AccountLocked, self::IpBlocked, self::SqlInjectionPattern,
            self::XssPattern, self::DatabaseSyntaxError, self::ScanningBehavior => Severity::High,
        };
    }

    public function category(): string
    {
        return explode('.', $this->value, 2)[0];
    }
}
