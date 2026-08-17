<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Service\Traits;

use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Trait TranslatorAwareTrait.
 */
trait TranslatorAwareTrait
{
    protected TranslatorInterface $translator;

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }
}
