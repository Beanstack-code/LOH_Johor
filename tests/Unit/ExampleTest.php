<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
}
