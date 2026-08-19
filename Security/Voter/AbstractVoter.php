<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Base class of every home-made voter.
 *
 * A concrete voter states three things and nothing else: the attributes it carries, the
 * subject types it applies to, and how it decides. Everything around that — Symfony's two
 * caching hooks, the anonymous-visitor guard, the role lookup — lives here, so a voter is
 * only ever business rules.
 *
 * ```php
 * final class GalleryVoter extends AbstractVoter
 * {
 *     public const string VIEW = 'GALLERY_VIEW';
 *
 *     protected function attributes(): array { return [self::VIEW]; }
 *
 *     protected function subjects(): array { return [Gallery::class]; }
 *
 *     protected function decide(string $attribute, mixed $subject, UserInterface $user): bool
 *     {
 *         return match ($attribute) {
 *             self::VIEW => $subject instanceof Gallery && $this->canView($subject, $user),
 *             default => false,
 *         };
 *     }
 * }
 * ```
 *
 * Generic so a consuming application can narrow the pair on its own base class and keep the
 * `@extends` annotations of its concrete voters meaningful.
 *
 * @template TAttribute of string
 * @template TSubject of mixed
 *
 * @extends Voter<TAttribute, TSubject>
 */
abstract class AbstractVoter extends Voter
{
    protected Security $security;

    /**
     * Setter injection so concrete voters keep their constructor for their own dependencies
     * instead of forwarding this one — the same idiom as the bundle's `*AwareTrait`.
     */
    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    /**
     * The attributes this voter carries, one per exposed action. Listed explicitly rather
     * than discovered by reflection over the class constants: a voter is then free to hold
     * constants that are not access attributes, and static analysis can follow the values.
     *
     * @return list<string>
     */
    abstract protected function attributes(): array;

    /**
     * The subject types these attributes apply to. Return an empty list when the decision
     * does not rest on an entity at all (a dashboard, a global listing).
     *
     * @return list<class-string>
     */
    abstract protected function subjects(): array;

    /**
     * The decision, once the account is known. An anonymous visitor never reaches this
     * method, so `$user` is always a real account — a route open to everyone does not go
     * through a voter, it says so with `#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]`.
     */
    abstract protected function decide(string $attribute, mixed $subject, UserInterface $user): bool;

    /**
     * Cached by Symfony: the voter is no longer called for attributes it does not carry.
     */
    #[\Override]
    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, $this->attributes(), true);
    }

    /**
     * Cached by Symfony too, on the subject *type* rather than the instance — which is why
     * declaring `subjects()` is worth more than an instance check alone.
     *
     * `$subjectType` is the output of `get_debug_type()`, so a missing subject arrives as the
     * string `'null'` and is always accepted: an attribute that carries no entity is a
     * first-class case.
     */
    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        $subjects = $this->subjects();

        if ([] === $subjects || 'null' === $subjectType) {
            return true;
        }

        foreach ($subjects as $class) {
            if ($subjectType === $class || is_subclass_of($subjectType, $class)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->supportsAttribute($attribute) && $this->supportsSubject($subject);
    }

    /**
     * Instance-level counterpart of {@see self::supportsType()}. Override it when the rule is
     * finer than a type — a state, a tenant — but keep it cheap: it runs on every check.
     */
    protected function supportsSubject(mixed $subject): bool
    {
        $subjects = $this->subjects();

        if ([] === $subjects || null === $subject) {
            return true;
        }

        foreach ($subjects as $class) {
            if ($subject instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decision for a visitor with no account. Refuses everything by default: a voter decides
     * for accounts, and a route open to everyone should say so with
     * `#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]` rather than route through a voter.
     *
     * Override it for the genuine exceptions — a published gallery a visitor may read, a
     * public profile. Keeping it separate from {@see self::decide()} is what lets `decide()`
     * type its `$user` parameter instead of re-checking for null on every branch.
     */
    protected function decideForAnonymous(string $attribute, mixed $subject): bool
    {
        return false;
    }

    #[\Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            $granted = $this->decideForAnonymous($attribute, $subject);

            if (!$granted) {
                $vote?->addReason('The voter requires an authenticated user.');
            }

            return $granted;
        }

        return $this->decide($attribute, $subject, $user);
    }

    /**
     * Role of the signed-in account, **inheritance included** (`role_hierarchy`), where
     * `$token->getRoleNames()` only ever returns the roles actually stored. That difference
     * is the whole reason this helper exists: a `ROLE_ADMIN` account granted `ROLE_USER`
     * through the hierarchy passes here and fails a raw `getRoleNames()` check.
     */
    protected function hasRole(string $role): bool
    {
        return $this->security->isGranted($role);
    }
}
