<?php

declare(strict_types=1);

namespace App\Security\Detection;

final class SqlInjectionDetector extends AbstractSignatureDetector
{
    private const RULES = [
        // ' or 1=1 | " or "a"="a | ') or ('x'='x
        'sqli.quote_tautology' => '/[\'"`]\s*\)*\s*(?:(?:or|and|xor)(?:\s+|(?=[\'"`(]))|(?:\|\||&&)\s*)\(*\s*[\'"`]?[\w.-]+[\'"`]?\s*(?:=|<>|!=|<=|>=|<|>|\blike\b|\bis\b|\bin\b|\bbetween\b)/u',
        // 1 or 1=1 | and 5=5
        'sqli.numeric_tautology' => '/\b(?:or|and|xor)\s+(\d+)\s*=\s*\1\b/u',
        'sqli.union_select' => '/\bunion\b(?:\s+(?:all|distinct))?\s*\(*\s*select\b/u',
        'sqli.stacked_query' => '/;\s*(?:drop\s+(?:table|database|schema|user|view)|delete\s+from|insert\s+into|update\s+[\w.`"\[\]]+\s+set|truncate\s+table|alter\s+(?:table|user|database)|create\s+(?:table|user|database|function|procedure)|exec(?:ute)?\s*(?:\(|xp_|sp_|master)|declare\s+@|shutdown\b|grant\s+\w)/u',
        'sqli.time_based' => '/(?:(?:[\'"`;)]|\b(?:and|or|select|if|then|when|else|\|\|)\b)\s*\(*\s*(?:sleep|pg_sleep|benchmark)\s*\(|waitfor\s+delay\s+[\'"`]|dbms_pipe\s*\.\s*receive_message|dbms_lock\s*\.\s*sleep)/u',
        // admin'-- | admin' # | 1'-- - | x'/*  (comment must end the value, to spare prose)
        'sqli.comment_termination' => '/[\'"`]\s*\)*\s*(?:(?:--|#)[\s+-]*$|\/\*)/u',
        'sqli.schema_enumeration' => '/\b(?:information_schema|pg_catalog|pg_shadow|sqlite_master|sqlite_schema|sysobjects|syscolumns|sys\s*\.\s*(?:tables|objects|columns|databases)|mysql\s*\.\s*user|all_tables|user_tables|all_tab_columns)\b/u',
        'sqli.file_or_command' => '/\b(?:load_file\s*\(|into\s+(?:out|dump)file\b|xp_cmdshell|sp_oacreate|sp_execute_external_script|utl_http|utl_inaddr|copy\s+[\w.]+\s+(?:from|to)\s+program)/u',
        'sqli.error_based' => '/\b(?:extractvalue|updatexml)\s*\(|\bfloor\s*\(\s*rand\s*\(|\bexp\s*\(\s*~/u',
        'sqli.subquery_injection' => '/[\'"`]\s*(?:\|\||\+|&|=|\band\b|\bor\b)\s*\(\s*select\b/u',
        'sqli.char_obfuscation' => '/\bgroup_concat\s*\(|\b(?:char|chr|nchar)\s*\(\s*\d+\s*(?:,\s*\d+\s*)+\)|\bconcat\s*\(\s*0x[0-9a-f]+/u',
    ];

    protected function category(): string
    {
        return 'sqli';
    }

    protected function rules(): array
    {
        return self::RULES;
    }

    /**
     * Also evaluates the input with inline/versioned SQL comments removed, which
     * defeats tricks such as "UNION/**\/SELECT" or "/*!50000SELECT*\/".
     */
    protected function variants(string $normalized): array
    {
        $withoutComments = (string) preg_replace('#/\*!?\d*|\*/#', ' ', $normalized);
        $withoutComments = trim((string) preg_replace('/\s+/', ' ', $withoutComments));

        return $withoutComments === $normalized
            ? [$normalized]
            : [$normalized, $withoutComments];
    }
}
