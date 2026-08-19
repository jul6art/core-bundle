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
                ->scalarNode('encryption_key')
                    ->info('Base64-encoded 32-byte key. Setting it registers Security\\Encryptor and the listener that feeds the encrypted_string DBAL type. Point it at an env var; never commit it.')
                    ->defaultNull()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
