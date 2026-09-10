<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/runtime.php';

final class RuntimeConfigurationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testProductionErrorsAreLoggedButNeverDisplayed(): void
    {
        $displayDetails = App\configureRuntime('production', sys_get_temp_dir() . '/moreplaylist-test.log');

        $this->assertFalse($displayDetails);
        $this->assertSame('0', ini_get('display_errors'));
        $this->assertSame('0', ini_get('display_startup_errors'));
        $this->assertSame('1', ini_get('log_errors'));
        $this->assertSame(E_ALL, error_reporting());
    }

    #[RunInSeparateProcess]
    public function testUnknownEnvironmentDefaultsToProductionBehavior(): void
    {
        $this->assertFalse(App\configureRuntime('staging', sys_get_temp_dir() . '/moreplaylist-test.log'));
        $this->assertSame('0', ini_get('display_errors'));
    }

    #[RunInSeparateProcess]
    public function testLocalEnvironmentEnablesSlimErrorDetailsOnly(): void
    {
        $this->assertTrue(App\configureRuntime('local', sys_get_temp_dir() . '/moreplaylist-test.log'));
        $this->assertSame('0', ini_get('display_errors'));
    }
}
