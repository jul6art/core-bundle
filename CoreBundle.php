<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle;

use Jul6Art\CoreBundle\DependencyInjection\Compiler\PurgeCommandPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class CoreBundle.
 */
class CoreBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new PurgeCommandPass());
    }
}
