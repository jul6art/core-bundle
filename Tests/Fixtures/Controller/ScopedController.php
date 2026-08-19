<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Controller;

use Jul6Art\CoreBundle\Controller\AbstractController;

/**
 * A controller of the *other* convention: its catalogue is named by the screen, so its keys
 * carry no domain prefix and `translationDomain()` decides for all of them.
 */
final class ScopedController extends AbstractController
{
    #[\Override]
    protected function translationDomain(): string
    {
        return 'profile';
    }

    public function success(string $message): void
    {
        $this->addSuccessFlash($message);
    }

    /** @param array<string, mixed> $parameters */
    public function translate(string $key, array $parameters = []): string
    {
        return $this->trans($key, $parameters);
    }
}
