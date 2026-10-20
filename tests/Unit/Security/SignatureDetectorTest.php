<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\Detection\InputNormalizer;
use App\Security\Detection\SqlInjectionDetector;
use App\Security\Detection\XssDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SignatureDetectorTest extends TestCase
{
    private SqlInjectionDetector $sqli;
    private XssDetector $xss;

    protected function setUp(): void
    {
        $normalizer = new InputNormalizer();
        $this->sqli = new SqlInjectionDetector($normalizer);
        $this->xss = new XssDetector($normalizer);
    }

    #[DataProvider('sqlInjectionPayloads')]
    public function test_detects_sql_injection(string $payload): void
    {
        self::assertNotNull($this->sqli->detect($payload), "Missed SQLi payload: {$payload}");
    }

    #[DataProvider('xssPayloads')]
    public function test_detects_xss(string $payload): void
    {
        self::assertNotNull($this->xss->detect($payload), "Missed XSS payload: {$payload}");
    }

    #[DataProvider('benignInputs')]
    public function test_ignores_ordinary_input(string $input): void
    {
        $sqli = $this->sqli->detect($input);
        $xss = $this->xss->detect($input);

        self::assertNull($sqli, "False positive {$sqli?->ruleId} on: {$input}");
        self::assertNull($xss, "False positive {$xss?->ruleId} on: {$input}");
    }

    public function test_reports_rule_and_matched_fragment(): void
    {
        $result = $this->sqli->detect('1 UNION SELECT username, password FROM users');

        self::assertNotNull($result);
        self::assertSame('sqli', $result->category);
        self::assertSame('sqli.union_select', $result->ruleId);
        self::assertSame('union select', $result->matched);
    }

    /** @return iterable<int, array{string}> */
    public static function sqlInjectionPayloads(): iterable
    {
        $payloads = [
            "' OR 1=1 --", "admin'--", "admin' #", "1' or '1'='1", "\" or \"a\"=\"a", "') or ('x'='x",
            "q'or'1'='1", "1 OR 1=1", "1 AND 5=5", "1 UNION SELECT username,password FROM users",
            "1 union all select null,null--", "1 UNION/**/SELECT 1", "1 /*!50000UNION*/ /*!50000SELECT*/ 1",
            '1; DROP TABLE users', '1; delete from users', "1' AND SLEEP(5)--", "1); waitfor delay '0:0:5'--",
            '1 and benchmark(5000000,md5(1))', "' union select table_name from information_schema.tables--",
            '1 and extractvalue(1,concat(0x7e,version()))', '%27%20OR%201%3D1--', '%2527%2520OR%25201%253D1',
            "' || (select password from users) || '", "1; exec xp_cmdshell 'whoami'", 'group_concat(table_name)',
            "1'%00 or 1=1", "1' or\t1=1", "1 AND 1=1\xff",
        ];

        foreach ($payloads as $p) {
            yield [$p];
        }
    }

    /** @return iterable<int, array{string}> */
    public static function xssPayloads(): iterable
    {
        $payloads = [
            '<script>alert(1)</script>', '<ScRiPt src=//evil/x.js>', '<img src=x onerror=alert(1)>',
            '<svg/onload=alert(1)>', '"><svg onload=confirm(document.domain)>', '<iframe src=javascript:alert(1)>',
            'javascript:alert(document.cookie)', '<a href="javascript:void(0)">x</a>',
            '&lt;script&gt;alert(1)&lt;/script&gt;', '%3Cscript%3Ealert(1)%3C%2Fscript%3E',
            '<body onload=alert`1`>', '<details open ontoggle=prompt(1)>', 'data:text/html;base64,PHNjcmlwdD4=',
            "'-alert(1)-'", '<object data=x>', "eval(atob('YWxlcnQoMSk='))",
            '<div style="width: expression(alert(1))">', '<iframe srcdoc="<script>">', "<scr\x00ipt>alert(1)</script>",
        ];

        foreach ($payloads as $p) {
            yield [$p];
        }
    }

    /** @return iterable<int, array{string}> */
    public static function benignInputs(): iterable
    {
        $inputs = [
            "Merhaba, Köksan'ın siparişini kontrol eder misiniz?", "O'Brien", "Ali'nin raporu",
            'mehmet.emin@koksan.com', 'Select the best option from the list',
            'Lütfen formu doldurup gönderin; update the list tomorrow', 'JavaScript: The Good Parts',
            'Please confirm (yes) by Friday', 'Using <b>bold</b> and <i>italic</i>', 'a = b + c',
            'He said "Evet" -- then left', 'price < 100 and > 50', 'I need sleep (8 hours)',
            'Facial expression (smile)', '1990-05-12', '+90 (532) 000 00 00', 'Union of the sets and selection',
            'drop by my office; delete the draft', '100% PET şişe', 'C# and F# are languages', "rock 'n' roll",
            "Toplantı 14:00'te; create a ticket for this", 'SELECT * FROM is a SQL statement', "it's 5 o'clock",
            '"Hello" #greeting', 'x = y or z', 'Ürün kodu: PET-500/A', '<p>Paragraf</p>', "Ahmet's or Mehmet's",
            'data: 2024 raporu', 'window size is 5', 'document title', 'The alert was raised',
        ];

        foreach ($inputs as $i) {
            yield [$i];
        }
    }
}
