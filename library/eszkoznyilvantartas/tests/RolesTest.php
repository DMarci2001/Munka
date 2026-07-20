<?php
use PHPUnit\Framework\TestCase;

final class RolesTest extends TestCase {
  public function testHierarchyIsRespected(): void {
    $this->assertTrue(Roles::atLeast('it_admin', 'user'));
    $this->assertTrue(Roles::atLeast('it_admin', 'storekeeper'));
    $this->assertTrue(Roles::atLeast('it_admin', 'it_admin'));
    $this->assertTrue(Roles::atLeast('storekeeper', 'user'));
    $this->assertTrue(Roles::atLeast('storekeeper', 'storekeeper'));
    $this->assertTrue(Roles::atLeast('user', 'user'));
  }

  public function testLowerRoleFailsHigherRequirement(): void {
    $this->assertFalse(Roles::atLeast('user', 'storekeeper'));
    $this->assertFalse(Roles::atLeast('user', 'it_admin'));
    $this->assertFalse(Roles::atLeast('storekeeper', 'it_admin'));
  }

  public function testIntToString(): void {
    $this->assertSame('user', Roles::intToString(0));
    $this->assertSame('storekeeper', Roles::intToString(1));
    $this->assertSame('it_admin', Roles::intToString(2));
  }
}
