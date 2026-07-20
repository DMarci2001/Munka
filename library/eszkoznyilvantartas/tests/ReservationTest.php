<?php
use PHPUnit\Framework\TestCase;

final class ReservationTest extends TestCase {
  protected function setUp(): void {
    reseed_fixtures();
  }
  protected function tearDown(): void {
    logout_session();
  }

  public function testReserveHappyPath(): void {
    login_as(1, 'user');
    $dev = Ops::reserveDevice(2, 'teszt foglalás');
    $this->assertSame('Lefoglalva', $dev['status']);
  }

  public function testDuplicateReservationRejected(): void {
    login_as(1, 'user');
    Ops::reserveDevice(2, null);
    login_as(2, 'user');
    $this->expectException(OpError::class);
    Ops::reserveDevice(2, null);
  }

  public function testCancelByReserverSucceeds(): void {
    login_as(1, 'user');
    Ops::reserveDevice(2, null);
    $dev = Ops::cancelReservation(2);
    $this->assertSame('Kivehető', $dev['status']);
  }

  public function testCancelByOtherPlainUserFails(): void {
    login_as(1, 'user');
    Ops::reserveDevice(2, null);
    login_as(2, 'user');
    $this->expectException(OpError::class);
    Ops::cancelReservation(2);
  }

  public function testCancelByStorekeeperSucceedsEvenIfNotReserver(): void {
    login_as(1, 'user');
    Ops::reserveDevice(2, null);
    login_as(3, 'storekeeper');
    $dev = Ops::cancelReservation(2);
    $this->assertSame('Kivehető', $dev['status']);
  }

  public function testCannotReserveAlreadyOccupiedDevice(): void {
    // device 1 is checked out (held by user 1), not free/in-stock.
    login_as(2, 'user');
    $this->expectException(OpError::class);
    Ops::reserveDevice(1, null);
  }
}
