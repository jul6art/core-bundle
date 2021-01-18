<?php

namespace Jul6Art\CoreBundle\Service\Traits;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Trait TranslatorAwareTrait.
 */
trait TranslatorAwareTrait
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @required
     */
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }
}
