<?php
namespace GlamLux\Test\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A minimal quality gate test to confirm PHPUnit runs correctly in the CI environment.
     */
    public function test_basic_assertion()
    {
        $this->assertTrue(true, 'PHPUnit is configured and executing successfully.');
    }
}
