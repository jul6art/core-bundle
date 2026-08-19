<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Service\Traits;

use Jul6Art\CoreBundle\Service\Traits\TokenStorageAwareTrait;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\CustomIdUser;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\WidgetUser;
use Jul6Art\CoreBundle\Tests\Fixtures\Service\AwareService;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversTrait(TokenStorageAwareTrait::class)]
final class TokenStorageAwareTraitTest extends TestCase
{
    public function testThereIsNoUserWithoutAToken(): void
    {
        $service = $this->serviceWithUser(null);

        self::assertNull($service->getCurrentUserOrNull());
        self::assertNull($service->getCurrentUserIdOrNull());
    }

    public function testItReturnsTheAuthenticatedUser(): void
    {
        $user = new WidgetUser(1);

        self::assertSame($user, $this->serviceWithUser($user)->getCurrentUserOrNull());
    }

    public function testItReadsTheIdentifierOfAUserCarryingOne(): void
    {
        self::assertSame(42, $this->serviceWithUser(new WidgetUser(42))->getCurrentUserIdOrNull());
    }

    public function testAnUnpersistedUserHasNoIdentifier(): void
    {
        self::assertNull($this->serviceWithUser(new WidgetUser())->getCurrentUserIdOrNull());
    }

    /**
     * UserInterface declares no getId(), so users without one must not blow up.
     */
    public function testAUserWithoutGetIdYieldsNoIdentifier(): void
    {
        $user = new InMemoryUser('bob', null);

        self::assertNull($this->serviceWithUser($user)->getCurrentUserIdOrNull());
    }

    #[DataProvider('identifiers')]
    public function testItNormalisesTheIdentifier(mixed $id, ?int $expected): void
    {
        self::assertSame($expected, $this->serviceWithUser(new CustomIdUser($id))->getCurrentUserIdOrNull());
    }

    /**
     * @return iterable<string, array{mixed, int|null}>
     */
    public static function identifiers(): iterable
    {
        yield 'int' => [7, 7];
        yield 'numeric string' => ['7', 7];
        yield 'numeric float' => [7.0, 7];
        yield 'uuid' => ['0199a1b2-c3d4-7000-8000-000000000000', null];
        yield 'null' => [null, null];
        yield 'empty string' => ['', null];
    }

    // ── impersonation ─────────────────────────────────────────────────────

    public function testAGenuineLoginHasNoOriginalUser(): void
    {
        self::assertNull($this->serviceWithUser(new WidgetUser(1))->getOriginalUserIdOrNull());
    }

    public function testThereIsNoOriginalUserWithoutAToken(): void
    {
        self::assertNull($this->serviceWithUser(null)->getOriginalUserIdOrNull());
    }

    /**
     * Le cas qui justifie la méthode : pendant un `_switch_user`, `getCurrentUserIdOrNull()`
     * rend le compte **usurpé**. S'arrêter là rend une piste d'audit mensongère — les deux
     * identités doivent être lisibles.
     */
    public function testAnImpersonationExposesBothTheImpersonatedAndTheOriginalUser(): void
    {
        $service = $this->serviceWithImpersonation(impersonated: new WidgetUser(7), original: new WidgetUser(1));

        self::assertSame(7, $service->getCurrentUserIdOrNull(), 'Le compte usurpé reste le compte courant.');
        self::assertSame(1, $service->getOriginalUserIdOrNull(), "L'administrateur d'origine doit être traçable.");
    }

    public function testAnOriginalUserWithoutGetIdYieldsNoIdentifier(): void
    {
        $service = $this->serviceWithImpersonation(
            impersonated: new WidgetUser(7),
            original: new InMemoryUser('admin', null),
        );

        self::assertNull($service->getOriginalUserIdOrNull());
    }

    private function serviceWithImpersonation(UserInterface $impersonated, UserInterface $original): AwareService
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new SwitchUserToken(
            $impersonated,
            'main',
            $impersonated->getRoles(),
            new UsernamePasswordToken($original, 'main', $original->getRoles()),
        ));

        $service = new AwareService();
        $service->setTokenStorage($tokenStorage);

        return $service;
    }

    private function serviceWithUser(?UserInterface $user): AwareService
    {
        $tokenStorage = new TokenStorage();

        if ($user instanceof UserInterface) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        $service = new AwareService();
        $service->setTokenStorage($tokenStorage);

        return $service;
    }
}
