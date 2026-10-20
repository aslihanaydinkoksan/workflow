<?php

declare(strict_types=1);

namespace App\Security\Detection;

final class XssDetector extends AbstractSignatureDetector
{
    private const RULES = [
        'xss.script_tag' => '/<\s*\/?\s*script\b/u',
        // <img src=x onerror=...> | <svg/onload=...> | <body onpageshow=...>
        'xss.event_handler' => '/<\s*[a-z][^>]*?[\s\/"\'`](on[a-z]{3,})\s*=/u',
        'xss.dangerous_tag' => '/<\s*(?:iframe|frame|frameset|object|embed|applet|base|meta|link|form|isindex|svg|math|template|portal)\b/u',
        // javascript:alert(1) | "javascript:document.location=..." — ignores prose like "JavaScript: the good parts"
        'xss.script_uri' => '/(?:^|[\s"\'=(<`])(?:javascript|vbscript|livescript)\s*:\s*[\w$.\[\]\'"`]+\s*[(=`]/u',
        'xss.data_uri' => '/data\s*:\s*(?:text\/html|image\/svg\+xml|application\/x?html)/u',
        'xss.dom_sink' => '/\bdocument\s*\.\s*(?:cookie|domain|write)\b|\bwindow\s*\.\s*location\b|\.innerhtml\s*=|\beval\s*\(|\bsettimeout\s*\(\s*[\'"`]|\bfromcharcode\s*\(|\batob\s*\(/u',
        'xss.dialog_probe' => '/\b(?:alert|prompt|confirm)(?:\(|`)\s*(?:[\'"`]?[\w\s.]{0,40}[\'"`]?|document\.\w+|window\.\w+)\s*(?:\)|`)/u',
        'xss.attribute_injection' => '/\b(?:srcdoc|formaction|xlink:href)\s*=|:\s*expression\s*\(|-moz-binding\s*:/u',
    ];

    protected function category(): string
    {
        return 'xss';
    }

    protected function rules(): array
    {
        return self::RULES;
    }
}
