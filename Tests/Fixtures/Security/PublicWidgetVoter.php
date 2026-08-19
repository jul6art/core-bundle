<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Security;

use Jul6Art\CoreBundle\Security\Voter\AbstractVoter;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A voter whose read attribute is legitimately open to visitors with no account — the
 * published-content case that decideForAnonymous() exists for.
 *
 * @extends AbstractVoter<string, Widget>
 */
final class PublicWidgetVoter extends AbstractVoter
{
    public const string VIEW = 'PUBLIC_WIDGET_VIEW';
    public const string EDIT = 'PUBLIC_WIDGET_EDIT';

    #[\Override]
    protected function attributes(): array
    {
        return [self::VIEW, self::EDIT];
    }

    #[\Override]
    protected function subjects(): array
    {
        return [Widget::class];
    }

    #[\Override]
    protected function decideForAnonymous(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute;
    }

    #[\Override]
    protected function decide(string $attribute, mixed $subject, UserInterface $user): bool
    {
        return true;
    }
}
