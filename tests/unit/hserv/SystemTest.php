<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use hserv\System;

#[CoversClass(System::class)]
final class SystemTest extends TestCase
{
    protected function tearDown(): void {
        // System creates an error handler
        restore_error_handler();
    }

    public function testCanInitializeWithoutDatabase(): void
    {
        $system = new System();
        $successful = $system->init(null, false);

        $this->assertTrue($successful);
    }
}
