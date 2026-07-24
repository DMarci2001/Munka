<?php
use PHPUnit\Framework\TestCase;

// Role-gated "Kivétel" (checkout) permission — UAT §21.
// Fixture felhasználók (lásd fixtures.php): user 1 = jog_eszkoznyilvantartas_kivetel
// megadva; user 2/5/6 = sima user, flag NÉLKÜL; user 3 = storekeeper; user 4 = it_admin.
final class PermissionsTest extends TestCase {
  protected function setUp(): void {
    reseed_fixtures();
  }
  protected function tearDown(): void {
    logout_session();
  }

  private function setKivetelFlag(int $userId, bool $granted): void {
    $json = $granted ? json_encode(['permissions' => ['jog_eszkoznyilvantartas_kivetel' => 1]]) : null;
    getDB()->prepare('UPDATE users SET permissions = ? WHERE id = ?')->execute([$json, $userId]);
  }

  public function testPlainUserWithoutFlagCannotCheckOutForThemselves(): void {
    login_as(2, 'user'); // fixture: no permissions granted
    $this->expectException(OpError::class);
    $this->expectExceptionMessage('Nincs jogosultságod eszköz kivételéhez.');
    Ops::moveAsset([
      'device_id' => 6, 'event_type' => 'check_out',
      'to_user_id' => 2, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
  }

  public function testGrantingFlagAllowsSelfCheckOut(): void {
    $this->setKivetelFlag(2, true);
    login_as(2, 'user');
    $dev = Ops::moveAsset([
      'device_id' => 6, 'event_type' => 'check_out',
      'to_user_id' => 2, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
    $this->assertSame('Kiadva', $dev['status']);
    $this->assertSame(2, $dev['holder_id']);
  }

  public function testRevokingFlagBlocksCheckOutAgain(): void {
    $this->setKivetelFlag(2, true);
    $this->setKivetelFlag(2, false);
    login_as(2, 'user');
    $this->expectException(OpError::class);
    Ops::moveAsset([
      'device_id' => 6, 'event_type' => 'check_out',
      'to_user_id' => 2, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
  }

  public function testStorekeeperCheckOutUnaffectedByMissingFlag(): void {
    // user 3 (storekeeper fixture) never has jog_eszkoznyilvantartas_kivetel set —
    // must still be able to check out on behalf of someone else.
    login_as(3, 'storekeeper');
    $dev = Ops::moveAsset([
      'device_id' => 6, 'event_type' => 'check_out',
      'to_user_id' => 2, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
    $this->assertSame(2, $dev['holder_id']);
  }

  public function testItAdminCheckOutUnaffectedByMissingFlag(): void {
    login_as(4, 'it_admin');
    $dev = Ops::moveAsset([
      'device_id' => 6, 'event_type' => 'check_out',
      'to_user_id' => 4, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
    $this->assertSame(4, $dev['holder_id']);
  }

  public function testAuthCanCheckOutReflectsFlagForPlainUser(): void {
    login_as(2, 'user');
    $this->assertFalse(Auth::canCheckOut());
    $this->setKivetelFlag(2, true);
    $this->assertTrue(Auth::canCheckOut());
  }

  public function testAuthCanCheckOutAlwaysTrueForStorekeeperAndItAdmin(): void {
    login_as(3, 'storekeeper');
    $this->assertTrue(Auth::canCheckOut());
    login_as(4, 'it_admin');
    $this->assertTrue(Auth::canCheckOut());
  }
}
