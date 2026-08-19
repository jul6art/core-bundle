<?php

declare(strict_types=1);

namespace Jul6Art\CoreBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration.
 */
class Configuration implements ConfigurationInterface
{
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('core');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('email_debug')
                    ->info('Forwards critical logs by email through Monolog.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('email_debug_from')
                    ->info('Sender address. Required when email_debug is enabled.')
                    ->defaultNull()
                ->end()
                ->scalarNode('email_debug_title')
                    ->info('Subject of the debug emails.')
                    ->defaultValue('An error occured')
                ->end()
                ->scalarNode('email_debug_to')
                    ->info('Recipient address. Required when email_debug is enabled.')
                    ->defaultNull()
                ->end()
                ->arrayNode('purge')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('batch_size')
                            ->info('Rows flushed at a time by core:purge. Lower it when the entities are heavy.')
                            ->min(1)
                            ->defaultValue(100)
                        ->end()
                        ->arrayNode('aliases')
                            ->info('Extra names core:purge answers to. Use it to keep a legacy name alive so a deployed crontab does not break.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('encryption_key')
                    ->info('Base64-encoded 32-byte key. Setting it registers Security\\Encryptor and the listener that feeds the encrypted_string DBAL type. Point it at an env var; never commit it.')
                    ->defaultNull()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
