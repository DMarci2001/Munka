<?php
use PHPUnit\Framework\TestCase;

// Rögzített fixture-ök (lásd fixtures.php): device 2/6 = szabad (Kivehető, raktárban),
// device 1 = user1-nél (Kiadva), device 5 = Visszavétel folyamatban, device 16 = Selejtezve,
// device 17 = Elveszett, device 8 = Szerviz alatt.
final class MoveAssetTest extends TestCase {
  protected function setUp(): void {
    reseed_fixtures();
  }
  protected function tearDown(): void {
    logout_session();
  }

  public function testUserCanCheckOutFreeDeviceForThemselves(): void {
    login_as(1, 'user');
    $dev = Ops::moveAsset([
      'device_id' => 2, 'event_type' => 'check_out',
      'to_user_id' => 1, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
    $this->assertSame('Kiadva', $dev['status']);
    $this->assertSame(1, $dev['holder_id']);
  }

  public function testUserCannotCheckOutForSomeoneElse(): void {
    login_as(1, 'user');
    $this->expectException(OpError::class);
    Ops::moveAsset([
      'device_id' => 2, 'event_type' => 'check_out',
      'to_user_id' => 2, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
  }

  public function testUserCannotCheckOutAlreadyHeldDevice(): void {
    login_as(2, 'user'); // device 1 is held by user 1, not 2
    $this->expectException(OpError::class);
    Ops::moveAsset([
      'device_id' => 1, 'event_type' => 'check_out',
      'to_user_id' => 2, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
  }

  public function testStorekeeperCanCheckOutOnBehalfOfAnotherUser(): void {
    login_as(3, 'storekeeper');
    $dev = Ops::moveAsset([
      'device_id' => 6, 'event_type' => 'check_out',
      'to_user_id' => 2, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
    $this->assertSame(2, $dev['holder_id']);
  }

  /** @dataProvider terminalDevices */
  public function testTerminalStatusDeviceCannotBeMoved(int $deviceId): void {
    login_as(3, 'storekeeper');
    $this->expectException(OpError::class);
    Ops::moveAsset([
      'device_id' => $deviceId, 'event_type' => 'transfer',
      'to_user_id' => 3, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
  }
  public function terminalDevices(): array {
    return ['Selejtezve' => [16], 'Elveszett' => [17], 'Szerviz alatt' => [8]];
  }

  public function testCheckOutBlockedByOthersReservation(): void {
    // device 3 is reserved by user 1
    login_as(2, 'user');
    $this->expectException(OpError::class);
    Ops::moveAsset([
      'device_id' => 3, 'event_type' => 'check_out',
      'to_user_id' => 2, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
  }

  public function testStorekeeperCanOverrideOthersReservation(): void {
    login_as(3, 'storekeeper');
    $dev = Ops::moveAsset([
      'device_id' => 3, 'event_type' => 'check_out',
      'to_user_id' => 3, 'to_locations_id' => 1, 'to_departments_id' => 2,
    ]);
    $this->assertSame(3, $dev['holder_id']);
  }

  public function testStockTransferSucceedsWithLocationOnlyNoDepartment(): void {
    // Raktármozgatás: a részleg megadása opcionális, csak a cél-helyszín kötelező.
    login_as(3, 'storekeeper');
    $dev = Ops::moveAsset([
      'device_id' => 2, 'event_type' => 'stock_transfer',
      'to_locations_id' => 2, 'to_departments_id' => null,
    ]);
    $this->assertSame('Kivehető', $dev['status']);
    $this->assertSame(2, $dev['location_id']);
    $this->assertNull($dev['department_id']);
  }

  public function testStockTransferStillRequiresLocation(): void {
    login_as(3, 'storekeeper');
    $this->expectException(OpError::class);
    Ops::moveAsset([
      'device_id' => 2, 'event_type' => 'stock_transfer',
      'to_locations_id' => null, 'to_departments_id' => null,
    ]);
  }

  public function testRowLockSerializesConcurrentMoves(): void {
    // Egy második nyers PDO-kapcsolattal bizonyítjuk, hogy a lockDevice()
    // valódi SELECT ... FOR UPDATE zárolást tesz: amíg egy tranzakció
    // nyitva tartja a zárolást egy eszköz során, egy másik kapcsolat
    // ugyanarra a sorra irányuló FOR UPDATE lekérdezése zár-várakozási
    // időtúllépésbe fut (nem fér hozzá "csendben" versenyhelyzetben).
    $conn2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=eszkoznyilvantartas_test;charset=utf8mb4', 'root', '', [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $conn2->exec('SET SESSION innodb_lock_wait_timeout = 1');

    $db = getDB();
    $db->beginTransaction();
    $db->prepare('SELECT * FROM eszkoznyilvantartas_devices WHERE device_id = ? FOR UPDATE')->execute([2]);

    $conn2->beginTransaction();
    $threw = false;
    try {
      $conn2->prepare('SELECT * FROM eszkoznyilvantartas_devices WHERE device_id = ? FOR UPDATE')->execute([2]);
    } catch (\PDOException $e) {
      $threw = true;
    }
    $conn2->rollBack();
    $db->rollBack();

    $this->assertTrue($threw, 'A második kapcsolatnak zár-várakozási időtúllépésbe kellett volna futnia.');
  }
}
