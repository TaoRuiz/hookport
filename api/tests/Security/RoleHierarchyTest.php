<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final class RoleHierarchyTest extends KernelTestCase
{
    public function testAdminCanManageUsersAndHasEveryDemoRight(): void
    {
        $adminRoles = $this->findRolesReachableFrom('ROLE_ADMIN');

        self::assertContains('ROLE_USER_MANAGE', $adminRoles);
        self::assertContains('ROLE_DEMO', $adminRoles);
    }

    public function testDemoCannotManageUsers(): void
    {
        self::assertNotContains('ROLE_USER_MANAGE', $this->findRolesReachableFrom('ROLE_DEMO'));
    }

    /**
     * @return list<string>
     */
    private function findRolesReachableFrom(string $role): array
    {
        /** @var RoleHierarchyInterface $roleHierarchy */
        $roleHierarchy = static::getContainer()->get('security.role_hierarchy');

        return $roleHierarchy->getReachableRoleNames([$role]);
    }
}
