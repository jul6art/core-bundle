<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Service\Traits;

use Doctrine\ORM\EntityManagerInterface;
use Faker\Generator;
use Jul6Art\CoreBundle\Service\Traits\EntityManagerAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\EventDispatcherAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\FakerAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\FlashBagAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\TokenStorageAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\TranslatorAwareTrait;
use Jul6Art\CoreBundle\Tests\Fixtures\Service\AwareService;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Covers what every *AwareTrait has in common: an autowirable setter and a typed
 * property. The "@required" annotation these used to carry is gone, so the
 * #[Required] attribute is the only thing making injection happen.
 */
#[CoversTrait(EntityManagerAwareTrait::class)]
#[CoversTrait(EventDispatcherAwareTrait::class)]
#[CoversTrait(FakerAwareTrait::class)]
#[CoversTrait(FlashBagAwareTrait::class)]
#[CoversTrait(TokenStorageAwareTrait::class)]
#[CoversTrait(TranslatorAwareTrait::class)]
final class AwareTraitsTest extends TestCase
{
    #[DataProvider('setters')]
    public function testTheSetterIsMarkedRequired(string $method): void
    {
        $attributes = new \ReflectionMethod(AwareService::class, $method)->getAttributes(Required::class);

        self::assertCount(1, $attributes, \sprintf('"%s()" must carry #[Required] to be autowired.', $method));
    }

    #[DataProvider('properties')]
    public function testThePropertyIsTyped(string $property, string $expectedType): void
    {
        $type = new \ReflectionProperty(AwareService::class, $property)->getType();

        self::assertNotNull($type, \sprintf('"$%s" must be typed.', $property));
        self::assertSame($expectedType, (string) $type);
    }

    public function testSetEntityManagerStoresTheManager(): void
    {
        $service = new AwareService();
        $entityManager = self::createStub(EntityManagerInterface::class);

        $service->setEntityManager($entityManager);

        self::assertSame($entityManager, $service->entityManager());
    }

    public function testSetEventDispatcherStoresTheDispatcher(): void
    {
        $service = new AwareService();
        $dispatcher = self::createStub(EventDispatcherInterface::class);

        $service->setEventDispatcher($dispatcher);

        self::assertSame($dispatcher, $service->eventDispatcher());
    }

    public function testSetRequestStackStoresTheStack(): void
    {
        $service = new AwareService();
        $requestStack = new RequestStack();

        $service->setRequestStack($requestStack);

        self::assertSame($requestStack, $service->requestStack());
    }

    public function testSetTokenStorageStoresTheStorage(): void
    {
        $service = new AwareService();
        $tokenStorage = self::createStub(TokenStorageInterface::class);

        $service->setTokenStorage($tokenStorage);

        self::assertSame($tokenStorage, $service->tokenStorage());
    }

    public function testSetTranslatorStoresTheTranslator(): void
    {
        $service = new AwareService();
        $translator = self::createStub(TranslatorInterface::class);

        $service->setTranslator($translator);

        self::assertSame($translator, $service->translator());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function setters(): iterable
    {
        yield 'entity manager' => ['setEntityManager'];
        yield 'event dispatcher' => ['setEventDispatcher'];
        yield 'faker' => ['setFaker'];
        yield 'request stack' => ['setRequestStack'];
        yield 'token storage' => ['setTokenStorage'];
        yield 'translator' => ['setTranslator'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function properties(): iterable
    {
        yield 'entityManager' => ['entityManager', EntityManagerInterface::class];
        yield 'eventDispatcher' => ['eventDispatcher', EventDispatcherInterface::class];
        yield 'faker' => ['faker', Generator::class];
        yield 'requestStack' => ['requestStack', RequestStack::class];
        yield 'tokenStorage' => ['tokenStorage', TokenStorageInterface::class];
        yield 'translator' => ['translator', TranslatorInterface::class];
    }
}
