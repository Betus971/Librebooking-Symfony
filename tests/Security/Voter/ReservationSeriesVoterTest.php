<?php

namespace App\Tests\Security\Voter;

use App\Entity\ReservationSeries;
use App\Entity\User;
use App\Security\Voter\ReservationSeriesVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ReservationSeriesVoterTest extends TestCase
{
    private function voter(array $grantedRoles = []): ReservationSeriesVoter
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => in_array($role, $grantedRoles, true)
        );

        return new ReservationSeriesVoter($security);
    }

    private function token(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    public function testSuperAdminCanManage(): void
    {
        $voter = $this->voter(['ROLE_SUPER_ADMIN']);

        $result = $voter->vote($this->token(new User()), new ReservationSeries(), [ReservationSeriesVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testPlainUserCannotManage(): void
    {
        $voter = $this->voter([]); // ni super-admin ni gestionnaire

        $result = $voter->vote($this->token(new User()), new ReservationSeries(), [ReservationSeriesVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testOwnerCanCancelOwnReservation(): void
    {
        $voter = $this->voter([]); // pas de rôle admin

        $owner = new User();
        $series = new ReservationSeries();
        $series->setOwner($owner);

        $result = $voter->vote($this->token($owner), $series, [ReservationSeriesVoter::CANCEL]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testNonOwnerWithoutRoleCannotCancel(): void
    {
        $voter = $this->voter([]);

        $series = new ReservationSeries();
        $series->setOwner($this->userWithId(1));

        $result = $voter->vote($this->token($this->userWithId(2)), $series, [ReservationSeriesVoter::CANCEL]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    private function userWithId(int $id): User
    {
        $user = new User();
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }
}
