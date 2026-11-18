<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use hserv\System;

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
?>
