<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\Tests\DependencyInjection;

use Jul6Art\CoreBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
