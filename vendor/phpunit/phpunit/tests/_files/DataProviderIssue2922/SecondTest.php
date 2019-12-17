<?php

namespace Foo\DataProviderIssue2922;

use PHPUnit\Framework\TestCase;

// the name of the class cannot match file name - if they match all is fine
class SecondCedarThemeTest extends TestCase
{
    public function testSecond()
    {
        $this->assertTrue(true);
    }
}
