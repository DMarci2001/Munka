<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/lib/Lookups.php';

// Átadás (transfer) megerősítési workflow — UAT §22/§23.
// Fixture: device 1 = user 1-nél (Kiadva, loc 1, dept 2).
final class TransferConfirmationTest extends TestCase {
  protected function setUp(): void {
    reseed_fixtures();
  }
  protected function tearDown(): void {
    logout_session();
  }

  private function pendingTransferEventId(int $deviceId): int {
    $ev = Repo::pendingTransfer($deviceId);
    $this->assertNotNull($ev, 'expected a pending transfer to exist');
    return (int) $ev['event_id'];
  }

  public function testUserTransferCreatesPendingStatusWithoutChangingCustody(): void {
    login_as(1, 'user');
    $dev = Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $this->assertSame('Átadás folyamatban', $dev['status']);
    // custody nem változott — a jelenlegi (confirmed) birtokos még mindig user 1
    $cur = Repo::currentState(1);
    $this->assertSame(1, $cur['holder']);
  }

  public function testStorekeeperTransferCompletesInstantlyNoPending(): void {
    login_as(3, 'storekeeper');
    $dev = Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $this->assertSame('Kiadva', $dev['status']);
    $this->assertSame(2, $dev['holder_id']);
    $this->assertNull(Repo::pendingTransfer(1));
  }

  public function testItAdminTransferCompletesInstantlyNoPending(): void {
    login_as(4, 'it_admin');
    $dev = Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $this->assertSame('Kiadva', $dev['status']);
    $this->assertNull(Repo::pendingTransfer(1));
  }

  public function testRecipientSeesIncomingPendingTransfer(): void {
    login_as(1, 'user');
    Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $incoming = Lookups::myPendingTransfers(2);
    $this->assertCount(1, $incoming);
    $this->assertSame(1, $incoming[0]['device_id']);
    $this->assertSame(1, (int) $incoming[0]['from_user_id']);
  }

  public function testRecipientConfirmTransfersCustodyAndClearsFromIncoming(): void {
    login_as(1, 'user');
    Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $eventId = $this->pendingTransferEventId(1);

    login_as(2, 'user'); // recipient
    $dev = Ops::confirmTransfer($eventId);
    $this->assertSame('Kiadva', $dev['status']);
    $this->assertSame(2, $dev['holder_id']);
    $this->assertEmpty(Lookups::myPendingTransfers(2));
  }

  public function testRecipientRejectKeepsOriginalHolderAndClearsFromIncoming(): void {
    login_as(1, 'user');
    Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $eventId = $this->pendingTransferEventId(1);

    login_as(2, 'user'); // recipient
    $dev = Ops::rejectTransfer($eventId, 'Nem kérem ezt az eszközt.');
    $this->assertSame('Kiadva', $dev['status']);
    $cur = Repo::currentState(1);
    $this->assertSame(1, $cur['holder']); // vissza az eredeti birtokoshoz
    $this->assertEmpty(Lookups::myPendingTransfers(2));
  }

  public function testRejectedTransferAppearsInReviewQueueDistinctFromCheckins(): void {
    login_as(1, 'user');
    Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $eventId = $this->pendingTransferEventId(1);
    login_as(2, 'user');
    Ops::rejectTransfer($eventId, 'ok');

    $queue = Lookups::reviewQueue();
    $kinds = array_column($queue, 'kind');
    $this->assertContains('rejected_transfer', $kinds);
    $row = array_values(array_filter($queue, fn($r) => $r['event_id'] == $eventId))[0];
    $this->assertSame('rejected_transfer', $row['kind']);
  }

  public function testStorekeeperAcceptRejectionResolvesWithoutChangingHolder(): void {
    login_as(1, 'user');
    Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $eventId = $this->pendingTransferEventId(1);
    login_as(2, 'user');
    Ops::rejectTransfer($eventId, 'ok');

    login_as(3, 'storekeeper');
    Ops::resolveRejectedTransfer($eventId, true);

    $cur = Repo::currentState(1);
    $this->assertSame(1, $cur['holder']); // változatlan
    $queue = Lookups::reviewQueue();
    $this->assertEmpty(array_filter($queue, fn($r) => $r['event_id'] == $eventId)); // eltűnt a sorból
  }

  public function testStorekeeperOverrideRejectionCreatesNewConfirmedTransferKeepingHistory(): void {
    login_as(1, 'user');
    Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $rejectedEventId = $this->pendingTransferEventId(1);
    login_as(2, 'user');
    Ops::rejectTransfer($rejectedEventId, 'ok');

    login_as(3, 'storekeeper');
    $dev = Ops::resolveRejectedTransfer($rejectedEventId, false); // felülbírálás
    $this->assertSame(2, $dev['holder_id']);
    $this->assertSame('Kiadva', $dev['status']);

    $history = Lookups::history(1);
    $original = array_values(array_filter($history, fn($e) => (int) $e['event_id'] === $rejectedEventId))[0];
    $this->assertSame('rejected', $original['confirmation_status']);
    $this->assertNotNull($original['resolved_at']); // lezárva, de MEGMARADT

    $newConfirmed = array_values(array_filter(
      $history,
      fn($e) => (int) $e['event_id'] !== $rejectedEventId && $e['event_type'] === 'transfer' && $e['confirmation_status'] === 'confirmed'
    ));
    $this->assertCount(1, $newConfirmed); // új, megerősített esemény jött létre — semmi nem lett felülírva
  }

  public function testSecondPendingTransferOnSameDeviceIsBlocked(): void {
    login_as(1, 'user');
    Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $this->expectException(OpError::class);
    $this->expectExceptionMessage('már van megerősítésre váró átadás');
    Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 5]);
  }

  public function testDeviceStatusBadgeShowsAtadasFolyamatbanWhilePending(): void {
    login_as(1, 'user');
    $dev = Ops::moveAsset(['device_id' => 1, 'event_type' => 'transfer', 'to_user_id' => 2]);
    $this->assertSame('Átadás folyamatban', $dev['status']);
    // újralekérve is stabil marad, amíg a döntés meg nem történik
    $this->assertSame('Átadás folyamatban', Repo::enrichOne(1)['status']);
  }
}
