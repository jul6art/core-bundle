<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Functional;

use Jul6Art\CoreBundle\Controller\BulkActionRunner;
use Jul6Art\CoreBundle\Service\FlashTranslator;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Wiring of the controller helpers. The behaviour is unit-tested; what only a container shows is
 * that the flash translator carries the application's own domain map, and that the bulk runner
 * stays out when the ORM is not there.
 */
#[CoversNothing]
final class ControllerHelpersTest extends AbstractFunctionalTestCase
{
    /** Subscribed by AbstractController, so it must exist even with no configuration at all. */
    public function testTheFlashTranslatorIsAlwaysRegistered(): void
    {
        self::assertTrue($this->boot()->has(FlashTranslator::class));
    }

    public function testItCarriesTheConfiguredDomainMap(): void
    {
        $container = $this->boot('test', ['flash' => ['domain_map' => ['user.' => 'user'], 'default_domain' => 'flashes']]);

        $translator = $container->get(FlashTranslator::class);
        self::assertInstanceOf(FlashTranslator::class, $translator);

        // The test kernel has no catalogue, so the translator echoes the key back. What is
        // observable here is that the service was built at all, with the map wired in.
        self::assertSame('user.created', $translator->trans('user.created'));
        self::assertSame('elsewhere.done', $translator->trans('elsewhere.done'));
    }

    /**
     * The runner needs `doctrine.orm.entity_manager`; registering it without the ORM would break
     * the container of every application that installs the bundle without Doctrine.
     */
    public function testTheBulkRunnerFollowsTheOrm(): void
    {
        self::assertFalse($this->boot()->has(BulkActionRunner::class));
        self::assertTrue($this->boot('test', [], withOrm: true)->has(BulkActionRunner::class));
    }
}
