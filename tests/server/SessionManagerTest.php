<?php

declare(strict_types=1);

use App\Utils\SessionManager;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SessionManagerTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testSetGetDeleteCycle(): void
    {
        $session = new SessionManager();

        $session->set('user_name', 'tester');
        $this->assertSame('tester', $session->get('user_name'));

        $session->delete('user_name');
        $this->assertNull($session->get('user_name'));
    }

    #[RunInSeparateProcess]
    public function testGetReturnsNullForMissingKey(): void
    {
        $session = new SessionManager();

        $this->assertNull($session->get('token'));
    }
}
