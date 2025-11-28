<?php

use PHPUnit\Framework\TestCase;
use OGP\OGP;

class OGPTest extends TestCase
{
    public function testConstruct()
    {
        $ogp = new OGP('127.0.0.1', 27015);
        $this->assertEquals('127.0.0.1', $ogp->addr);
        $this->assertEquals(27015, $ogp->port);
        $this->assertEquals(100, $ogp->timeout);
    }

    public function testParseLimitType()
    {
        $ogp = new OGP('127.0.0.1', 27015);
        $this->assertEquals('Time (in seconds)', $ogp->parseLimitType(0));
        $this->assertEquals('Player Score', $ogp->parseLimitType(1));
        $this->assertEquals('Round', $ogp->parseLimitType(2));
        $this->assertEquals('Team Score', $ogp->parseLimitType(3));
        $this->assertEquals('Unknown', $ogp->parseLimitType(99));
    }

    public function testGetErrorbyID()
    {
        $ogp = new OGP('127.0.0.1', 27015);
        $this->assertEquals('0 - Banned', $ogp->getErrorbyID(0));
        $this->assertEquals('1 - Invalid Type: The query type in header is unkown', $ogp->getErrorbyID(1));
        $this->assertStringContainsString('Unknown', $ogp->getErrorbyID(99));
    }

    public function testColorConversion()
    {
        $ogp = new OGP('127.0.0.1', 27015);
        // Test white
        $white16 = $ogp->color_web_to16bit('FFFFFF');
        $whiteWeb = $ogp->color_16bit_toweb($white16);
        $this->assertEquals('FFFFFF', $whiteWeb);

        // Test black
        $black16 = $ogp->color_web_to16bit('000000');
        $blackWeb = $ogp->color_16bit_toweb($black16);
        $this->assertEquals('000000', $blackWeb);
    }
}
