<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Gadget;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * `JSON_TEXT()` executed for real. The unit test asserts the SQL each platform gets; this
 * one proves the function parses, walks and runs end to end — a wrong token sequence in
 * parse() produces no SQL error, just a DQL that never matches.
 */
#[CoversNothing]
final class DqlFunctionTest extends AbstractFunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $container = $this->boot('test', [], withOrm: true);

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        new SchemaTool($this->entityManager)->createSchema([
            $this->entityManager->getClassMetadata(Gadget::class),
        ]);
    }

    public function testJsonTextMakesAJsonArrayColumnSearchableWithLike(): void
    {
        $this->gadget(['ROLE_ADMIN', 'ROLE_USER']);
        $this->gadget(['ROLE_USER']);

        self::assertCount(1, $this->findByTag('ROLE_ADMIN'));
        self::assertCount(2, $this->findByTag('ROLE_USER'));
        self::assertCount(0, $this->findByTag('ROLE_GHOST'));
    }

    public function testJsonTextMatchesNothingOnAnEmptyArray(): void
    {
        $this->gadget([]);

        self::assertCount(0, $this->findByTag('ROLE_USER'));
    }

    /** @param list<string> $tags */
    private function gadget(array $tags): void
    {
        $this->entityManager->persist(new Gadget()->setTags($tags));
        $this->entityManager->flush();
    }

    /** @return list<Gadget> */
    private function findByTag(string $tag): array
    {
        /** @var list<Gadget> $result */
        $result = $this->entityManager
            ->createQuery(\sprintf('SELECT g FROM %s g WHERE JSON_TEXT(g.tags) LIKE :needle', Gadget::class))
            ->setParameter('needle', \sprintf('%%"%s"%%', $tag))
            ->getResult();

        return $result;
    }
}
