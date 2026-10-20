<?php

declare(strict_types=1);

namespace App\Security\Detection;

/**
 * Identifies well-known offensive tools by their default User-Agent.
 * Trivial to spoof, so treat it as a low-cost early signal, not proof.
 */
final class ScannerUserAgentDetector
{
    private const TOOLS = [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'zgrab', 'nuclei', 'acunetix', 'netsparker', 'invicti',
        'wpscan', 'joomscan', 'dirbuster', 'dirb', 'gobuster', 'feroxbuster', 'ffuf', 'wfuzz',
        'openvas', 'nessus', 'qualys', 'arachni', 'w3af', 'whatweb', 'havij', 'commix', 'xsstrike',
        'dalfox', 'skipfish', 'jaeles', 'zap', 'burpcollaborator', 'hydra', 'medusa',
    ];

    private readonly string $pattern;

    public function __construct()
    {
        $alternatives = implode('|', array_map(static fn (string $t): string => preg_quote($t, '/'), self::TOOLS));
        $this->pattern = '/(?<![a-z0-9])(' . $alternatives . ')(?![a-z0-9])/i';
    }

    /**
     * @return string|null The matched tool name, lower-cased.
     */
    public function detect(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return preg_match($this->pattern, $userAgent, $match) === 1 ? strtolower($match[1]) : null;
    }
}
