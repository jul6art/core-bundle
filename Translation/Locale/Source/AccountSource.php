<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Translation\Locale\Source;

use Jul6Art\CoreBundle\Translation\Locale\LocaleSourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The language saved on the signed-in account, which follows the person from one device to the next.
 *
 * Read through PropertyAccess (`core.locale.account_property`, `locale` by default): the account
 * implements nothing for it, and an account of another class — a portal's customer, a machine — that
 * has no such property simply has no say.
 */
final readonly class AccountSource implements LocaleSourceInterface
{
    public function __construct(
        private PropertyAccessorInterface $propertyAccessor,
        private string $property = 'locale',
    ) {
    }

    #[\Override]
    public function locale(Request $request, ?UserInterface $user): ?string
    {
        if (null === $user || !$this->propertyAccessor->isReadable($user, $this->property)) {
            return null;
        }

        $locale = $this->propertyAccessor->getValue($user, $this->property);

        return \is_string($locale) ? $locale : null;
    }
}
