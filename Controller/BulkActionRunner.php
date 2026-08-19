<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The bulk-action pattern of a data table, in one place: validate the CSRF token, parse `ids[]`,
 * load every row in **one** query, check the voter row by row, run a business callable on each,
 * and swallow a `\DomainException` per row so one refusal does not cost the rest of the batch.
 *
 * ```php
 * #[Route('/bulk-publish', methods: ['POST'])]
 * #[IsGranted(PermissionCodes::CMS_PAGE_PUBLISH)]
 * public function bulkPublish(Request $request, BulkActionRunner $runner): Response
 * {
 *     $count = $runner->run($request, Page::class, PageVoter::PUBLISH, function (Page $page): void {
 *         if ($page->isPublished()) {
 *             throw new \DomainException('Already published.');   // this row only
 *         }
 *         $this->pageService->publish($page);
 *     }, csrfTokenId: 'bulk_action');
 *
 *     return $this->redirectWithSuccess('cms_page_index', 'cms.page.bulk_published', ['%count%' => $count]);
 * }
 * ```
 *
 * Two deliberate choices in the transaction handling:
 *
 * - **one enveloping transaction** rather than one per row, which divides the transactional
 *   noise of a 25-row batch by about three;
 * - a `\DomainException` continues the loop because it is a business refusal, while any other
 *   throwable **rolls the whole batch back** — a database error signals corruption that must not
 *   leave a half-persisted state.
 */
final class BulkActionRunner
{
    /**
     * @param string $softDeleteFilterName Doctrine filter suspended by {@see runOnSoftDeleted()}.
     *                                     Match the name declared in `doctrine.orm.filters`.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly string $softDeleteFilterName = 'soft_delete',
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T>          $entityClass    FQCN to look up
     * @param string                   $voterAttribute checked against every row
     * @param callable(T, ?int): mixed $action         run for each granted row, with the actor id
     *
     * @return int rows the action ran on
     */
    public function run(
        Request $request,
        string $entityClass,
        string $voterAttribute,
        callable $action,
        string $csrfTokenId = 'bulk_action',
    ): int {
        $this->assertCsrfToken($request, $csrfTokenId);

        $ids = self::selectedIds($request);

        if ([] === $ids) {
            return 0;
        }

        /** @var list<T> $entities */
        $entities = $this->entityManager->getRepository($entityClass)->findBy(['id' => $ids]);

        if ([] === $entities) {
            return 0;
        }

        return $this->apply($entities, $voterAttribute, $action);
    }

    /**
     * Variant for restoring soft-deleted rows: suspends the soft-delete filter for the fetch —
     * the rows would be invisible otherwise — and skips anything that is not actually deleted,
     * so restoring an already-active row is a silent no-op rather than an error.
     *
     * @template T of object
     *
     * @param class-string<T>          $entityClass
     * @param string                   $voterAttribute usually the RESTORE attribute
     * @param callable(T, ?int): mixed $action
     *
     * @return int rows the action ran on
     */
    public function runOnSoftDeleted(
        Request $request,
        string $entityClass,
        string $voterAttribute,
        callable $action,
        string $csrfTokenId = 'bulk_action',
    ): int {
        $this->assertCsrfToken($request, $csrfTokenId);

        $ids = self::selectedIds($request);

        if ([] === $ids) {
            return 0;
        }

        $filters = $this->entityManager->getFilters();
        $wasEnabled = $filters->isEnabled($this->softDeleteFilterName);

        if ($wasEnabled) {
            $filters->disable($this->softDeleteFilterName);
        }

        try {
            /** @var list<T> $entities */
            $entities = $this->entityManager->getRepository($entityClass)->findBy(['id' => $ids]);

            $deleted = array_values(array_filter(
                $entities,
                static fn (object $entity): bool => method_exists($entity, 'getDeletedAt') && null !== $entity->getDeletedAt(),
            ));

            return $this->apply($deleted, $voterAttribute, $action);
        } finally {
            if ($wasEnabled) {
                $filters->enable($this->softDeleteFilterName);
            }
        }
    }

    private function assertCsrfToken(Request $request, string $csrfTokenId): void
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($csrfTokenId, $request->request->getString('_token')))) {
            throw new BadRequestException('Invalid CSRF token.');
        }
    }

    /**
     * @return list<int>
     */
    private static function selectedIds(Request $request): array
    {
        return array_values(array_unique(array_filter(
            array_map(intval(...), $request->request->all('ids')),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /**
     * @template T of object
     *
     * @param list<T>                  $entities
     * @param callable(T, ?int): mixed $action
     */
    private function apply(array $entities, string $voterAttribute, callable $action): int
    {
        if ([] === $entities) {
            return 0;
        }

        $actorId = $this->actorId();
        $count = 0;

        $this->entityManager->beginTransaction();

        try {
            foreach ($entities as $entity) {
                if (!$this->security->isGranted($voterAttribute, $entity)) {
                    continue;
                }

                try {
                    $action($entity, $actorId);
                    ++$count;
                } catch (\DomainException) {
                    // A business refusal on one row — an invoice already paid, a page already
                    // published. The rest of the batch is unaffected.
                    continue;
                }
            }

            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            throw $e;
        }

        return $count;
    }

    private function actorId(): ?int
    {
        $actor = $this->security->getUser();

        if (!\is_object($actor) || !method_exists($actor, 'getId')) {
            return null;
        }

        $id = $actor->getId();

        return is_numeric($id) ? (int) $id : null;
    }
}
