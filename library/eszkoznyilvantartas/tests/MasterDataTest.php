<?php
use PHPUnit\Framework\TestCase;

final class MasterDataTest extends TestCase {
  protected function setUp(): void {
    reseed_fixtures();
  }
  protected function tearDown(): void {
    logout_session();
  }

  public function testAddLocationHappyPath(): void {
    login_as(4, 'it_admin');
    $loc = Ops::addLocation(['address' => '6720 Szeged, Teszt utca 1.']);
    $this->assertSame('6720 Szeged, Teszt utca 1.', $loc['address']);
  }

  public function testAddDepartmentHappyPath(): void {
    login_as(3, 'storekeeper');
    $dept = Ops::addDepartment(['locations_id' => 1, 'name' => 'Új osztály', 'type' => 'osztály']);
    $this->assertSame(1, $dept['locations_id']);
  }

  public function testAddDepartmentRejectsUnknownLocation(): void {
    login_as(3, 'storekeeper');
    $this->expectException(OpError::class);
    Ops::addDepartment(['locations_id' => 9999, 'name' => 'Sehol részleg', 'type' => 'osztály']);
  }

  public function testAddDepartmentRejectsInvalidType(): void {
    login_as(3, 'storekeeper');
    $this->expectException(OpError::class);
    Ops::addDepartment(['locations_id' => 1, 'name' => 'Rossz típus', 'type' => 'garázs']);
  }

  public function testAddDeviceTypeHappyPath(): void {
    login_as(4, 'it_admin');
    $type = Ops::addDeviceType(['type' => 'Defibrillátor', 'description' => 'AED']);
    $this->assertSame('Defibrillátor', $type['type']);
  }

  public function testAddAttrDefHappyPath(): void {
    login_as(4, 'it_admin');
    $attr = Ops::addAttrDef([
      'device_type_id' => 1, 'attribute_key' => 'weight_kg',
      'label' => 'Súly (kg)', 'data_type' => 'decimal',
    ]);
    $this->assertIsInt($attr['id']);
  }

  public function testAddAttrDefRejectsUnknownDeviceType(): void {
    login_as(4, 'it_admin');
    $this->expectException(OpError::class);
    Ops::addAttrDef([
      'device_type_id' => 9999, 'attribute_key' => 'weight_kg',
      'label' => 'Súly (kg)', 'data_type' => 'decimal',
    ]);
  }

  public function testAddAttrDefGeneralAllowsNullDeviceType(): void {
    login_as(4, 'it_admin');
    $attr = Ops::addAttrDef([
      'device_type_id' => null, 'attribute_key' => 'purchase_order',
      'label' => 'Beszerzési rendelés', 'data_type' => 'text',
    ]);
    $this->assertIsInt($attr['id']);
  }
}
