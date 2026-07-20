<?php
use PHPUnit\Framework\TestCase;

final class RegisterEditDeviceTest extends TestCase {
  protected function setUp(): void {
    reseed_fixtures();
    login_as(3, 'storekeeper');
  }
  protected function tearDown(): void {
    logout_session();
  }

  public function testRegisterHappyPath(): void {
    $dev = Ops::registerDevice([
      'device_type_id' => 1, 'asset_tag' => 'ESZ-NEW-001',
      'initial_location' => 1, 'initial_department' => 1,
    ]);
    $this->assertSame('ESZ-NEW-001', $dev['asset_tag']);
    $this->assertSame('Kivehető', $dev['status']);
  }

  public function testDuplicateAssetTagRejectedByPreCheck(): void {
    $this->expectException(OpError::class);
    $this->expectExceptionMessageMatches('/már létezik/');
    Ops::registerDevice(['device_type_id' => 1, 'asset_tag' => 'ESZ-0001']);
  }

  public function testDuplicateAssetTagRejectedCaseInsensitively(): void {
    $this->expectException(OpError::class);
    Ops::registerDevice(['device_type_id' => 1, 'asset_tag' => 'esz-0001']);
  }

  public function testRegisterRejectsUnknownDeviceType(): void {
    $this->expectException(OpError::class);
    Ops::registerDevice(['device_type_id' => 9999, 'asset_tag' => 'ESZ-BAD-TYPE']);
  }

  public function testEditHappyPath(): void {
    $dev = Ops::editDevice(1, ['manufacturer' => 'Új gyártó', 'condition' => 'Kopott']);
    $this->assertSame('Új gyártó', $dev['manufacturer']);
    $this->assertSame('Kopott', $dev['condition']);
  }

  public function testEditRejectsInvalidCondition(): void {
    $this->expectException(OpError::class);
    Ops::editDevice(1, ['condition' => 'Szuperjó']);
  }

  public function testEditRejectsUnknownDeviceType(): void {
    $this->expectException(OpError::class);
    Ops::editDevice(1, ['device_type_id' => 9999]);
  }

  public function testEditCannotChangeStatusDirectly(): void {
    // A "status" mezőt szándékosan nem engedjük szerkeszteni itt (A5) —
    // csak a dedikált műveleteken (moveAsset, retire, stb.) keresztül változhat.
    $before = Ops::editDevice(1, ['status' => 'Selejtezve']);
    $this->assertSame('Kiadva', $before['status']);
  }
}
