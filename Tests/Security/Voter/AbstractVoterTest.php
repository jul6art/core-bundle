<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Security\Voter;

use Jul6Art\CoreBundle\Security\Voter\AbstractVoter;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Gadget;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\WidgetUser;
use Jul6Art\CoreBundle\Tests\Fixtures\Security\DashboardVoter;
use Jul6Art\CoreBundle\Tests\Fixtures\Security\PublicWidgetVoter;
use Jul6Art\CoreBundle\Tests\Fixtures\Security\WidgetVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(AbstractVoter::class)]
final class AbstractVoterTest extends TestCase
{
    // ── supportsAttribute() : le hook de cache de Symfony ─────────────────

    public function testItCarriesOnlyItsOwnAttributes(): void
    {
        $voter = $this->widgetVoter();

        self::assertTrue($voter->supportsAttribute(WidgetVoter::VIEW));
        self::assertTrue($voter->supportsAttribute(WidgetVoter::EDIT));
        self::assertFalse($voter->supportsAttribute('SOMETHING_ELSE'));
    }

    /**
     * Only the constants listed by attributes() count. A voter is free to declare other
     * constants — the reflection-based approach this replaces would have turned every one of
     * them into an attribute.
     */
    public function testAnUndeclaredAttributeIsNotSupportedEvenIfItIsAClassConstant(): void
    {
        self::assertFalse($this->widgetVoter()->supportsAttribute('WIDGET_VIEW '));
    }

    // ── supportsType() : le second hook de cache ──────────────────────────

    public function testItSupportsItsSubjectTypeAndSubclassesOfIt(): void
    {
        $voter = $this->widgetVoter();

        self::assertTrue($voter->supportsType(Widget::class));
        self::assertFalse($voter->supportsType(Gadget::class));
        self::assertFalse($voter->supportsType('string'));
    }

    /** An attribute that carries no entity must still reach the voter. */
    public function testItSupportsTheAbsenceOfASubject(): void
    {
        self::assertTrue($this->widgetVoter()->supportsType('null'));
    }

    public function testAVoterWithoutDeclaredSubjectsSupportsEveryType(): void
    {
        $voter = $this->dashboardVoter();

        self::assertTrue($voter->supportsType(Widget::class));
        self::assertTrue($voter->supportsType('string'));
        self::assertTrue($voter->supportsType('null'));
    }

    // ── vote() : le comportement observable de bout en bout ───────────────

    public function testItAbstainsOnAnAttributeItDoesNotCarry(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->widgetVoter()->vote($this->token(), new Widget(), ['SOMETHING_ELSE'])
        );
    }

    public function testItAbstainsOnASubjectOfTheWrongType(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->widgetVoter()->vote($this->token(), new Gadget(), [WidgetVoter::VIEW])
        );
    }

    public function testItGrantsWhenTheDecisionIsPositive(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->widgetVoter()->vote($this->token(), new Widget(), [WidgetVoter::VIEW])
        );
    }

    public function testItDeniesWhenTheDecisionIsNegative(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->widgetVoter(hasRole: false)->vote($this->token(), new Widget(), [WidgetVoter::EDIT])
        );
    }

    public function testTheDecisionReceivesTheAttributeTheSubjectAndTheUser(): void
    {
        $voter = $this->widgetVoter();
        $widget = new Widget('audited');

        $voter->vote($this->token(), $widget, [WidgetVoter::VIEW]);

        self::assertSame([[WidgetVoter::VIEW, $widget, 'widget-user']], $voter->decisions);
    }

    /**
     * decide() must never have to re-check for an anonymous visitor: the base class refuses
     * before delegating, so a concrete voter can type its user parameter.
     */
    public function testAnAnonymousVisitorIsRefusedBeforeTheDecisionRuns(): void
    {
        $voter = $this->widgetVoter();

        $result = $voter->vote(new NullToken(), new Widget(), [WidgetVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
        self::assertSame([], $voter->decisions, 'decide() must not have been called.');
    }

    /** Symfony 7.3 lets a voter explain itself; the refusal above says why. */
    public function testTheRefusalOfAnAnonymousVisitorIsExplained(): void
    {
        $vote = new Vote();

        $this->widgetVoter()->vote(new NullToken(), new Widget(), [WidgetVoter::VIEW], $vote);

        self::assertCount(1, $vote->reasons);
        self::assertStringContainsString('authenticated', $vote->reasons[0]);
    }

    /**
     * Public read paths exist — a published gallery a visitor may browse — and they must not
     * have to reimplement voteOnAttribute() to keep working.
     */
    public function testAVoterMayOpenSomeAttributesToAnonymousVisitors(): void
    {
        $voter = new PublicWidgetVoter();
        $voter->setSecurity(self::createStub(Security::class));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote(new NullToken(), new Widget(), [PublicWidgetVoter::VIEW])
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new NullToken(), new Widget(), [PublicWidgetVoter::EDIT])
        );
    }

    /** An attribute granted to a visitor carries no refusal reason. */
    public function testAnAnonymousGrantIsNotExplainedAsARefusal(): void
    {
        $voter = new PublicWidgetVoter();
        $voter->setSecurity(self::createStub(Security::class));
        $vote = new Vote();

        $voter->vote(new NullToken(), new Widget(), [PublicWidgetVoter::VIEW], $vote);

        self::assertSame([], $vote->reasons);
    }

    public function testASubjectlessAttributeIsDecidedOnTheRolesAlone(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->dashboardVoter()->vote($this->token(), null, [DashboardVoter::ACCESS])
        );
    }

    // ── hasRole() ─────────────────────────────────────────────────────────

    /**
     * The role check goes through Security::isGranted(), not $token->getRoleNames(): only the
     * former follows `role_hierarchy`, so a role inherited but not stored still counts.
     * Proven end to end against a real container by RoleHierarchyTest.
     */
    public function testTheRoleCheckIsDelegatedToTheSecurityHelper(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('isGranted')->with('ROLE_EDITOR')->willReturn(true);

        $voter = new WidgetVoter();
        $voter->setSecurity($security);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token(), new Widget(), [WidgetVoter::EDIT])
        );
    }

    private function widgetVoter(bool $hasRole = true): WidgetVoter
    {
        $security = self::createStub(Security::class);
        $security->method('isGranted')->willReturn($hasRole);

        $voter = new WidgetVoter();
        $voter->setSecurity($security);

        return $voter;
    }

    private function dashboardVoter(bool $hasRole = true): DashboardVoter
    {
        $security = self::createStub(Security::class);
        $security->method('isGranted')->willReturn($hasRole);

        $voter = new DashboardVoter();
        $voter->setSecurity($security);

        return $voter;
    }

    private function token(): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new WidgetUser(1), 'main', ['ROLE_USER']);
    }
}
