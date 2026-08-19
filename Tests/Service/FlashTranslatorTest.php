<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\Service;

use Jul6Art\CoreBundle\Service\FlashTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Picks the translation domain from the key's prefix, which is what lets a controller write
 * `addSuccessFlash('user.created')` without naming a domain — and without every flash landing
 * in `messages`, the dumping ground.
 */
#[CoversClass(FlashTranslator::class)]
final class FlashTranslatorTest extends TestCase
{
    public function testAMappedPrefixSelectsItsDomain(): void
    {
        self::assertSame('user|user.created', $this->translator(['user.' => 'user'])->trans('user.created'));
    }

    public function testAnUnmappedKeyFallsBackToTheDefaultDomain(): void
    {
        self::assertSame('messages|something.else', $this->translator(['user.' => 'user'])->trans('something.else'));
    }

    /** An application that maps nothing gets plain Symfony behaviour. */
    public function testWithoutAMapEverythingGoesToTheDefaultDomain(): void
    {
        self::assertSame('messages|user.created', $this->translator()->trans('user.created'));
    }

    public function testTheDefaultDomainIsConfigurable(): void
    {
        self::assertSame('flashes|user.created', $this->translator([], 'flashes')->trans('user.created'));
    }

    /**
     * The first matching prefix wins, so a longer prefix has to be declared before the shorter
     * one it starts with — otherwise `organization.domain.` would never be reached.
     */
    public function testTheFirstMatchingPrefixWins(): void
    {
        $map = ['organization.domain.' => 'domain', 'organization.' => 'organization'];

        self::assertSame('domain|organization.domain.added', $this->translator($map)->trans('organization.domain.added'));
        self::assertSame('organization|organization.renamed', $this->translator($map)->trans('organization.renamed'));
    }

    public function testParametersAreForwarded(): void
    {
        self::assertSame('user|user.created|%name%=Ada', $this->translator(['user.' => 'user'])->trans('user.created', ['%name%' => 'Ada']));
    }

    public function testAnExplicitDomainShortCircuitsTheMap(): void
    {
        // Le contrôleur qui nomme son domaine gagne contre la carte : sans ce court-circuit, une
        // clé comme 'user.created' partirait dans 'user' alors que le contrôleur a demandé
        // 'profile'.
        $translator = $this->translator(['user.' => 'user']);

        self::assertSame('profile|user.created', $translator->trans('user.created', [], 'profile'));
    }

    public function testAnExplicitDomainAlsoBeatsTheDefault(): void
    {
        self::assertSame('profile|edit.success', $this->translator([])->trans('edit.success', [], 'profile'));
    }

    /** @param array<string, string> $domainMap */
    private function translator(array $domainMap = [], string $defaultDomain = 'messages'): FlashTranslator
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $parameters, ?string $domain): string {
                $rendered = ($domain ?? 'null').'|'.$id;

                foreach ($parameters as $key => $value) {
                    $rendered .= '|'.$key.'='.(\is_scalar($value) ? (string) $value : '?');
                }

                return $rendered;
            }
        );

        return new FlashTranslator($translator, $domainMap, $defaultDomain);
    }
}
