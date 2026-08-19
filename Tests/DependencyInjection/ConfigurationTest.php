<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\DependencyInjection;

use Jul6Art\CoreBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testItsRootNodeIsCore(): void
    {
        $tree = new Configuration()->getConfigTreeBuilder()->buildTree();

        self::assertSame('core', $tree->getName());
    }

    public function testItAppliesDefaultsWhenNothingIsConfigured(): void
    {
        self::assertSame([
            'email_debug' => false,
            'email_debug_from' => null,
            'email_debug_title' => 'An error occured',
            'email_debug_to' => null,
            'number_format' => [
                'decimal_separator' => ',',
                'thousands_separator' => "\u{00A0}",
                'decimals' => 2,
            ],
            'form' => ['number_grouping' => false],
            'pdf' => ['public_dir' => '%kernel.project_dir%/public'],
            'security_headers' => [
                'enabled' => false,
                'csp_enforce' => false,
                'csp_policy' => null,
                'headers' => [],
            ],
            'captcha' => [
                'operations' => ['+'],
                'session_key' => '_math_captcha_answer',
            ],
            'purge' => ['batch_size' => 100, 'aliases' => []],
            'encryption_key' => null,
        ], $this->process([]));
    }

    public function testItKeepsTheConfiguredValues(): void
    {
        $config = $this->process([[
            'email_debug' => true,
            'email_debug_from' => 'from@example.com',
            'email_debug_title' => 'Boom',
            'email_debug_to' => 'to@example.com',
        ]]);

        self::assertTrue($config['email_debug']);
        self::assertSame('from@example.com', $config['email_debug_from']);
        self::assertSame('Boom', $config['email_debug_title']);
        self::assertSame('to@example.com', $config['email_debug_to']);
    }

    /**
     * The key must survive processing verbatim: the extension passes it straight to the
     * service argument so an `%env(...)%` placeholder is resolved at runtime, not baked
     * into the compiled container.
     */
    public function testTheEncryptionKeyIsKeptVerbatim(): void
    {
        self::assertSame('%env(APP_ENCRYPTION_KEY)%', $this->process([['encryption_key' => '%env(APP_ENCRYPTION_KEY)%']])['encryption_key']);
    }

    public function testThePurgeBatchSizeIsConfigurable(): void
    {
        $purge = $this->process([['purge' => ['batch_size' => 25]]])['purge'];

        self::assertIsArray($purge);
        self::assertSame(25, $purge['batch_size']);
    }

    /**
     * `csp_enforce` is a scalar node so a deployment can drive it from the environment; the
     * placeholder has to survive processing untouched to be resolved at runtime.
     */
    public function testTheCspEnforceFlagAcceptsAnEnvPlaceholder(): void
    {
        $headers = $this->process([['security_headers' => ['csp_enforce' => '%env(bool:CSP_ENFORCE)%']]])['security_headers'];

        self::assertIsArray($headers);
        self::assertSame('%env(bool:CSP_ENFORCE)%', $headers['csp_enforce']);
    }

    /** Header overrides must survive processing verbatim, including their casing. */
    public function testHeaderOverridesAreKeptAsGiven(): void
    {
        $headers = $this->process([['security_headers' => ['headers' => ['X-Frame-Options' => 'SAMEORIGIN']]]])['security_headers'];

        self::assertIsArray($headers);
        self::assertSame(['X-Frame-Options' => 'SAMEORIGIN'], $headers['headers']);
    }

    /** A legacy name kept alive so a deployed crontab survives a rename. */
    public function testPurgeAliasesAreKept(): void
    {
        $purge = $this->process([['purge' => ['aliases' => ['app:purge']]]])['purge'];

        self::assertIsArray($purge);
        self::assertSame(['app:purge'], $purge['aliases']);
    }

    /** A batch of zero would flush on every row; the constraint says so rather than surprising. */
    public function testAZeroBatchSizeIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([['purge' => ['batch_size' => 0]]]);
    }

    public function testLaterConfigsOverrideEarlierOnes(): void
    {
        $config = $this->process([
            ['email_debug' => false, 'email_debug_title' => 'first'],
            ['email_debug' => true],
        ]);

        self::assertTrue($config['email_debug']);
        self::assertSame('first', $config['email_debug_title']);
    }

    /**
     * email_debug is a booleanNode, so it no longer silently accepts arbitrary scalars.
     */
    #[DataProvider('nonBooleanValues')]
    public function testItRejectsNonBooleanEmailDebug(mixed $value): void
    {
        $this->expectException(InvalidTypeException::class);

        $this->process([['email_debug' => $value]]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonBooleanValues(): iterable
    {
        yield 'string' => ['yes'];
        yield 'int' => [1];
        yield 'array' => [[]];
    }

    /**
     * @param array<int, array<string, mixed>> $configs
     *
     * @return array<array-key, mixed>
     */
    private function process(array $configs): array
    {
        return new Processor()->processConfiguration(new Configuration(), $configs);
    }
}
