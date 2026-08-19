<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Security;

use Jul6Art\CoreBundle\Security\Voter\AbstractVoter;
use Jul6Art\CoreBundle\Tests\Fixtures\Entity\Widget;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A voter bound to one subject type, the ordinary case.
 *
 * @extends AbstractVoter<string, Widget>
 */
final class WidgetVoter extends AbstractVoter
{
    public const string VIEW = 'WIDGET_VIEW';
    public const string EDIT = 'WIDGET_EDIT';

    /** @var list<array{string, mixed, string}> */
    public array $decisions = [];

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
    protected function decide(string $attribute, mixed $subject, UserInterface $user): bool
    {
        $this->decisions[] = [$attribute, $subject, $user->getUserIdentifier()];

        return self::VIEW === $attribute || $this->hasRole('ROLE_EDITOR');
    }
}
