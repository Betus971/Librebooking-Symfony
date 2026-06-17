<?php

namespace App\Tests\Domain\Reservation;

use App\Domain\Reservation\ReservationWorkflow;
use App\Entity\ReservationSeries;
use App\Entity\ReservationStatus;
use App\Repository\ReservationInstanceRepository;
use App\Repository\ReservationSeriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ReservationWorkflowTest extends TestCase
{
    private function workflow(bool $hasUpcoming = true, bool $requiresApproval = true): ReservationWorkflow
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $instances = $this->createStub(ReservationInstanceRepository::class);
        $instances->method('hasUpcoming')->willReturn($hasUpcoming);

        $seriesRepo = $this->createStub(ReservationSeriesRepository::class);
        $seriesRepo->method('requiresApproval')->willReturn($requiresApproval);

        return new ReservationWorkflow($em, $instances, $seriesRepo);
    }

    private function series(int $statusId): ReservationSeries
    {
        $status = $this->createStub(ReservationStatus::class);
        $status->method('getId')->willReturn($statusId);

        $series = $this->createStub(ReservationSeries::class);
        $series->method('getStatus')->willReturn($status);

        return $series;
    }

    public function testCanApproveOnlyFromPending(): void
    {
        $wf = $this->workflow();

        self::assertTrue($wf->can('approve', $this->series(ReservationStatus::PENDING)));
        self::assertFalse($wf->can('approve', $this->series(ReservationStatus::APPROVED)));
    }

    public function testCanRejectOnlyFromPending(): void
    {
        $wf = $this->workflow();

        self::assertTrue($wf->can('reject', $this->series(ReservationStatus::PENDING)));
        self::assertFalse($wf->can('reject', $this->series(ReservationStatus::CANCELLED)));
    }

    public function testCanCancelFromPendingOrApproved(): void
    {
        $wf = $this->workflow();

        self::assertTrue($wf->can('cancel', $this->series(ReservationStatus::PENDING)));
        self::assertTrue($wf->can('cancel', $this->series(ReservationStatus::APPROVED)));
        self::assertFalse($wf->can('cancel', $this->series(ReservationStatus::CANCELLED)));
    }

    public function testEnsureAllowedThrowsWhenReservationIsPast(): void
    {
        // hasUpcoming = false → approve/reject impossibles sur du passé.
        $wf = $this->workflow(hasUpcoming: false);

        $this->expectException(\DomainException::class);
        $wf->ensureAllowed('approve', $this->series(ReservationStatus::PENDING));
    }

    public function testEnsureAllowedApprovePassesForUpcomingPendingThatRequiresApproval(): void
    {
        $wf = $this->workflow(hasUpcoming: true, requiresApproval: true);

        $this->expectNotToPerformAssertions();
        $wf->ensureAllowed('approve', $this->series(ReservationStatus::PENDING));
    }

    public function testEnsureAllowedApproveThrowsWhenApprovalNotRequired(): void
    {
        $wf = $this->workflow(hasUpcoming: true, requiresApproval: false);

        $this->expectException(\DomainException::class);
        $wf->ensureAllowed('approve', $this->series(ReservationStatus::PENDING));
    }
}
