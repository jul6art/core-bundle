<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\WidgetUser;
use Jul6Art\CoreBundle\Tests\Fixtures\Security\DashboardVoter;
use Jul6Art\CoreBundle\Tests\Fixtures\Security\WidgetVoter;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * AbstractVoter against a real container. Two things cannot be proven with mocks: that the
 * `#[Required]` setter is actually wired by autoconfiguration, and that `hasRole()` follows
 * `role_hierarchy` — the very reason it goes through Security::isGranted() instead of
 * reading `$token->getRoleNames()`.
 */
#[CoversNothing]
final class VoterTest extends AbstractFunctionalTestCase
{
    private ContainerInterface $container;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = $this->boot();
    }

    public function testTheSecurityHelperIsInjectedByAutoconfiguration(): void
    {
        $voter = $this->container->get(WidgetVoter::class);

        self::assertInstanceOf(WidgetVoter::class, $voter);
        // Reaching hasRole() at all proves the setter ran: the property is typed and
        // non-nullable, so an un-injected voter would raise an Error here.
        self::assertTrue($voter->supportsAttribute(WidgetVoter::VIEW));
    }

    /**
     * The account stores ROLE_ADMIN only. ROLE_EDITOR is reachable through the hierarchy, so
     * the voter must grant — while a raw `getRoleNames()` check would refuse.
     */
    public function testHasRoleFollowsTheRoleHierarchy(): void
    {
        $this->authenticateWith(['ROLE_ADMIN']);

        self::assertTrue($this->checker()->isGranted(WidgetVoter::EDIT, new Widget()));
    }

    public function testTheInheritedRoleIsNotAmongTheStoredOnes(): void
    {
        $token = $this->authenticateWith(['ROLE_ADMIN']);

        self::assertNotContains('ROLE_EDITOR', $token->getRoleNames(), 'Otherwise the test above proves nothing.');
    }

    public function testAnAccountWithoutTheRoleIsRefused(): void
    {
        $this->authenticateWith(['ROLE_USER']);

        self::assertFalse($this->checker()->isGranted(WidgetVoter::EDIT, new Widget()));
    }

    /** The hierarchy also feeds a subject-less voter. */
    public function testASubjectlessVoterIsGrantedThroughTheHierarchy(): void
    {
        $this->authenticateWith(['ROLE_ADMIN']);

        self::assertTrue($this->checker()->isGranted(DashboardVoter::ACCESS));
    }

    public function testAnAttributeCarriedByNoVoterIsRefused(): void
    {
        $this->authenticateWith(['ROLE_ADMIN']);

        self::assertFalse($this->checker()->isGranted('NOBODY_CARRIES_THIS', new Widget()));
    }

    /** @param list<string> $roles */
    private function authenticateWith(array $roles): UsernamePasswordToken
    {
        $token = new UsernamePasswordToken(new WidgetUser(1), 'main', $roles);

        $storage = $this->container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $storage);
        $storage->setToken($token);

        return $token;
    }

    private function checker(): AuthorizationCheckerInterface
    {
        $checker = $this->container->get('security.authorization_checker');
        self::assertInstanceOf(AuthorizationCheckerInterface::class, $checker);

        return $checker;
    }
}
