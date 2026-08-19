<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Fixtures\Security;

use Jul6Art\CoreBundle\Security\Voter\AbstractVoter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A voter whose attributes carry no entity at all — `subjects()` is empty, so any subject
 * (including none) is accepted and the decision rests on the attribute and the roles.
 *
 * @extends AbstractVoter<string, mixed>
 */
final class DashboardVoter extends AbstractVoter
{
    public const string ACCESS = 'DASHBOARD_ACCESS';

    #[\Override]
    protected function attributes(): array
    {
        return [self::ACCESS];
    }

    #[\Override]
    protected function subjects(): array
    {
        return [];
    }

    #[\Override]
    protected function decide(string $attribute, mixed $subject, UserInterface $user): bool
    {
        return $this->hasRole('ROLE_USER');
    }
}
