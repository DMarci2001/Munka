<?php
use PHPUnit\Framework\TestCase;

// Device 5 fixture: 'Visszavétel folyamatban', egy függő check_in eseménnyel
// (holder=5-től induló leadás dept 6-ba). Lásd fixtures.php.
final class ConfirmRejectCheckInTest extends TestCase {
  private PDO $db;

  protected function setUp(): void {
    reseed_fixtures();
    $this->db = getDB();
  }
  protected function tearDown(): void {
    logout_session();
  }

  private function pendingEventId(int $deviceId): int {
    $st = $this->db->prepare(
      "SELECT event_id FROM eszkoznyilvantartas_device_custody_events
       WHERE device_id = ? AND confirmation_status = 'pending' AND event_type = 'check_in'"
    );
    $st->execute([$deviceId]);
    return (int) $st->fetchColumn();
  }

  public function testConfirmHappyPath(): void {
    login_as(3, 'storekeeper');
    $eventId = $this->pendingEventId(5);
    $dev = Ops::confirmCheckIn($eventId);
    $this->assertSame('Kivehető', $dev['status']);
  }

  public function testRejectHappyPath(): void {
    login_as(3, 'storekeeper');
    $eventId = $this->pendingEventId(5);
    $dev = Ops::rejectCheckIn($eventId, 'nincs meg fizikailag');
    $this->assertSame('Kiadva', $dev['status']);
  }

  public function testConfirmFailsIfDeviceStatusChangedWhilePending(): void {
    login_as(3, 'storekeeper');
    $eventId = $this->pendingEventId(5);
    // Közben valaki elveszettnek jelöli az eszközt.
    Ops::markLost(5, 'közben elveszett');
    $this->expectException(OpError::class);
    Ops::confirmCheckIn($eventId);
  }

  public function testRejectFailsIfDeviceStatusChangedWhilePending(): void {
    login_as(3, 'storekeeper');
    $eventId = $this->pendingEventId(5);
    Ops::sendToRepair(5, 1, 8, 'közben szervizbe kellett küldeni');
    $this->expectException(OpError::class);
    Ops::rejectCheckIn($eventId, 'indok');
  }

  // Megjegyzés: a "user szerepkör nem erősítheti meg" eset NEM tesztelhető itt
  // Ops::confirmCheckIn() hívásán keresztül, mert Auth::requireRole() sikertelen
  // jogosultság esetén json_error()-t hív, ami `exit`-tel leállítja a PHP
  // folyamatot (a PHPUnit futtatót is). A szerepkör-határ lefedettsége a
  // RolesTest::testLowerRoleFailsHigherRequirement()-ben van, ami közvetlenül
  // a tiszta Roles::atLeast()-et teszteli exit() nélkül.
}
