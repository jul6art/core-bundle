<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Test;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The production configuration hardens HTTP: listed hosts, proxies that are really proxies, the
 * security headers and the secrets guard on ({@see HttpHardeningRules}).
 *
 * A project extends it once, in `tests/Architecture/`, and adds nothing:
 *
 * ```php
 * final class HttpHardeningTest extends HttpHardeningTestCase
 * {
 * }
 * ```
 *
 * The rules read the configuration the `prod` environment writes, without booting it
 * ({@see KernelConfiguration}): a setting moved under `when@dev` by mistake turns this test red.
 */
abstract class HttpHardeningTestCase extends KernelTestCase
{
    public function testTheProductionConfigurationHardensHttp(): void
    {
        $configuration = KernelConfiguration::of(static::getKernelClass(), 'prod');

        $violations = HttpHardeningRules::violations($configuration->extension('framework'), $configuration->extension('core'));

        self::assertSame([], $violations, implode("\n", $violations));
    }
}
