<?php
use PHPUnit\Framework\TestCase;

final class RepairLostFoundTest extends TestCase {
  protected function setUp(): void {
    reseed_fixtures();
    login_as(3, 'storekeeper');
  }
  protected function tearDown(): void {
    logout_session();
  }

  public function testSendToRepairHappyPath(): void {
    $dev = Ops::sendToRepair(2, 1, 8, 'nem kapcsol be');
    $this->assertSame('Szerviz alatt', $dev['status']);
  }

  public function testSendToRepairRejectsUnknownDepartment(): void {
    $this->expectException(OpError::class);
    Ops::sendToRepair(2, 1, 9999, null);
  }

  public function testSendToRepairRejectsUnknownLocation(): void {
    $this->expectException(OpError::class);
    Ops::sendToRepair(2, 9999, 8, null);
  }

  // Részleg nélkül: sendToRepair automatikusan az első 'műhely' típusú
  // részleget választja (nem a helyszín szerintit) - lásd Ops::sendToRepair,
  // ez a meglévő null-dept auto-választás, nem a jelen módosítás tárgya.
  public function testSendToRepairSucceedsWithLocationOnlyNoDepartment(): void {
    $dev = Ops::sendToRepair(2, 1, null, 'nem kapcsol be');
    $this->assertSame('Szerviz alatt', $dev['status']);
  }

  public function testReturnFromRepairHappyPath(): void {
    // device 8 fixture status: 'Szerviz alatt'
    $dev = Ops::returnFromRepair(8, 1, 1, 'megjavítva');
    $this->assertSame('Kivehető', $dev['status']);
  }

  public function testReturnFromRepairFailsWhenNotInRepair(): void {
    $this->expectException(OpError::class);
    Ops::returnFromRepair(2, 1, 1, null); // device 2 is not 'Szerviz alatt'
  }

  public function testReturnFromRepairRejectsUnknownDepartment(): void {
    $this->expectException(OpError::class);
    Ops::returnFromRepair(8, 1, 9999, null);
  }

  public function testReturnFromRepairSucceedsWithLocationOnlyNoDepartment(): void {
    $dev = Ops::returnFromRepair(8, 1, null, 'megjavítva');
    $this->assertSame('Kivehető', $dev['status']);
    $this->assertNull($dev['department_id']);
  }

  public function testMarkLostHappyPath(): void {
    $dev = Ops::markLost(2, 'nem található');
    $this->assertSame('Elveszett', $dev['status']);
  }

  public function testMarkFoundHappyPath(): void {
    $dev = Ops::markFound(17, 1, 1, 'megkerült');
    $this->assertSame('Kivehető', $dev['status']);
  }

  public function testMarkFoundRejectsUnknownDepartment(): void {
    $this->expectException(OpError::class);
    Ops::markFound(17, 1, 9999, null);
  }

  public function testMarkFoundSucceedsWithLocationOnlyNoDepartment(): void {
    $dev = Ops::markFound(17, 1, null, 'megkerült');
    $this->assertSame('Kivehető', $dev['status']);
    $this->assertNull($dev['department_id']);
  }
}
