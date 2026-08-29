<?php

namespace Tests\Unit;

use App\Support\UserAgentParser;
use PHPUnit\Framework\TestCase;

class UserAgentParserTest extends TestCase
{
    private const CHROME_MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    private const GOOGLEBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    public function test_parses_a_desktop_chrome_agent(): void
    {
        $parsed = UserAgentParser::parse(self::CHROME_MAC);

        $this->assertSame('Chrome', $parsed['browser']);
        $this->assertSame('Desktop', $parsed['device']);
        $this->assertSame('macOS', $parsed['os']);
        $this->assertFalse($parsed['is_bot']);
    }

    public function test_parses_a_mobile_safari_agent(): void
    {
        $parsed = UserAgentParser::parse(self::SAFARI_IPHONE);

        $this->assertSame('Safari', $parsed['browser']);
        $this->assertSame('Mobile', $parsed['device']);
        $this->assertSame('iOS', $parsed['os']);
        $this->assertFalse($parsed['is_bot']);
    }

    public function test_flags_and_names_a_crawler(): void
    {
        $parsed = UserAgentParser::parse(self::GOOGLEBOT);

        $this->assertTrue($parsed['is_bot']);
        $this->assertSame('Googlebot', $parsed['browser']);
        $this->assertSame('Bot', $parsed['device']);
    }

    public function test_flags_script_clients_as_bots(): void
    {
        $this->assertTrue(UserAgentParser::isBot('curl/8.6.0'));
        $this->assertTrue(UserAgentParser::isBot('python-requests/2.32.0'));
    }

    public function test_empty_agent_is_unknown_not_bot(): void
    {
        $parsed = UserAgentParser::parse(null);

        $this->assertSame('Unknown', $parsed['browser']);
        $this->assertFalse($parsed['is_bot']);
    }
}
