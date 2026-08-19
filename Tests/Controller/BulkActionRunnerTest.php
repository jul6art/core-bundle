<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Jul6Art\CoreBundle\Controller\BulkActionRunner;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\WidgetUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The bulk-action contract: refuse without a CSRF token, load every row in one query, check the
 * voter row by row, and let a single business refusal fail without aborting the batch.
 */
#[CoversClass(BulkActionRunner::class)]
final class BulkActionRunnerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    /** @var list<Widget> */
    private array $acted = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->acted = [];
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testAnInvalidTokenIsRefusedBeforeAnythingElse(): void
    {
        $this->entityManager->expects(self::never())->method('getRepository');

        $this->expectException(BadRequestException::class);

        $this->runner(tokenValid: false)->run($this->request([1, 2]), Widget::class, 'EDIT', $this->action());
    }

    public function testAnEmptySelectionDoesNothing(): void
    {
        $this->entityManager->expects(self::never())->method('getRepository');

        self::assertSame(0, $this->runner()->run($this->request([]), Widget::class, 'EDIT', $this->action()));
    }

    /** Ids arrive as strings from a form; anything that is not a positive integer is dropped. */
    public function testNonPositiveIdsAreDiscarded(): void
    {
        $this->entityManager->expects(self::never())->method('getRepository');

        self::assertSame(0, $this->runner()->run($this->request(['0', '-3', 'abc', '']), Widget::class, 'EDIT', $this->action()));
    }

    /** One SELECT for N rows, not N — the reason this helper exists rather than a loop of find(). */
    public function testEveryRowIsLoadedInASingleQuery(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findBy')->with(['id' => [1, 2, 3]])->willReturn([]);
        $this->entityManager->expects(self::once())->method('getRepository')->with(Widget::class)->willReturn($repository);

        $this->runner()->run($this->request([1, 2, 2, 3]), Widget::class, 'EDIT', $this->action());
    }

    public function testEveryGrantedRowIsActedUpon(): void
    {
        $this->givenRows(new Widget('a'), new Widget('b'));

        self::assertSame(2, $this->runner()->run($this->request([1, 2]), Widget::class, 'EDIT', $this->action()));
        self::assertCount(2, $this->acted);
    }

    public function testARowTheVoterRefusesIsSkippedSilently(): void
    {
        $this->givenRows(new Widget('allowed'), new Widget('refused'));

        $count = $this->runner(grantedNames: ['allowed'])->run($this->request([1, 2]), Widget::class, 'EDIT', $this->action());

        self::assertSame(1, $count);
        self::assertSame(['allowed'], array_map(static fn (Widget $w): string => $w->getName(), $this->acted));
    }

    /**
     * A business rule refusing one row — an invoice already paid — must not cost the other
     * twenty-four rows of the batch.
     */
    public function testABusinessRefusalOnOneRowDoesNotAbortTheBatch(): void
    {
        $this->givenRows(new Widget('ok-1'), new Widget('locked'), new Widget('ok-2'));

        $count = $this->runner()->run($this->request([1, 2, 3]), Widget::class, 'EDIT', function (Widget $widget): void {
            if ('locked' === $widget->getName()) {
                throw new \DomainException('Already processed.');
            }

            $this->acted[] = $widget;
        });

        self::assertSame(2, $count);
    }

    /** A database error is a different matter: the whole batch rolls back. */
    public function testADatabaseErrorRollsTheWholeBatchBack(): void
    {
        $this->givenRows(new Widget('a'));
        $this->entityManager->expects(self::once())->method('rollback');
        $this->entityManager->expects(self::never())->method('commit');

        $this->expectException(\RuntimeException::class);

        $this->runner()->run($this->request([1]), Widget::class, 'EDIT', static function (): never {
            throw new \RuntimeException('deadlock');
        });
    }

    public function testTheWholeBatchRunsInOneTransaction(): void
    {
        $this->givenRows(new Widget('a'), new Widget('b'));
        $this->entityManager->expects(self::once())->method('beginTransaction');
        $this->entityManager->expects(self::once())->method('commit');

        $this->runner()->run($this->request([1, 2]), Widget::class, 'EDIT', $this->action());
    }

    /** The action receives the actor's id, so it can stamp who did what. */
    public function testTheActionReceivesTheActorId(): void
    {
        $this->givenRows(new Widget('a'));
        $seen = null;

        $this->runner(actorId: 42)->run($this->request([1]), Widget::class, 'EDIT', static function (Widget $w, ?int $actorId) use (&$seen): void {
            $seen = $actorId;
        });

        self::assertSame(42, $seen);
    }

    public function testTheCsrfTokenIdIsPerCall(): void
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects(self::once())->method('isTokenValid')
            ->with(self::callback(static fn (CsrfToken $token): bool => 'bulk_publish' === $token->getId()))
            ->willReturn(true);

        $this->entityManager->expects(self::never())->method('getRepository');

        new BulkActionRunner($this->entityManager, $this->security(), $csrf)
            ->run($this->request([]), Widget::class, 'EDIT', $this->action(), 'bulk_publish');
    }

    /** @param list<string>|null $grantedNames null grants everything */
    private function runner(bool $tokenValid = true, ?array $grantedNames = null, ?int $actorId = null): BulkActionRunner
    {
        $csrf = self::createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($tokenValid);

        return new BulkActionRunner($this->entityManager, $this->security($grantedNames, $actorId), $csrf);
    }

    /** @param list<string>|null $grantedNames */
    private function security(?array $grantedNames = null, ?int $actorId = null): Security
    {
        $security = self::createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute, mixed $subject = null): bool => null === $grantedNames
                || ($subject instanceof Widget && \in_array($subject->getName(), $grantedNames, true))
        );
        $security->method('getUser')->willReturn(null === $actorId ? null : new WidgetUser($actorId));

        return $security;
    }

    private function givenRows(Widget ...$widgets): void
    {
        $repository = self::createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($widgets);
        // A real expectation, not a convenience: one repository lookup per batch.
        $this->entityManager->expects(self::once())->method('getRepository')->willReturn($repository);
    }

    /** @param list<int|string> $ids */
    private function request(array $ids): Request
    {
        return Request::create('/bulk', Request::METHOD_POST, ['_token' => 'whatever', 'ids' => $ids]);
    }

    /** @return callable(Widget, ?int): void */
    private function action(): callable
    {
        return function (Widget $widget): void {
            $this->acted[] = $widget;
        };
    }
}
