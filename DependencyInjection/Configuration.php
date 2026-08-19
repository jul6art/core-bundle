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
                ->arrayNode('security_headers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Off by default: installing this bundle must not change the responses of an application that did not ask for these headers. A real boolean only — it decides whether the listener is registered at all, which happens at compile time, so an env var cannot answer it.')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('csp_enforce')
                            ->info('false sends Content-Security-Policy-Report-Only. Watch the reports, then flip it. Scalar rather than boolean so it accepts %env(bool:…)%, resolved at runtime.')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('csp_policy')
                            ->info('Full policy string. null uses the bundle default, which keeps connect-src closed.')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('headers')
                            ->info('Overrides merged over the defaults. A null value drops that header entirely.')
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('captcha')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('operations')
                            ->info('Arithmetic operations the question may use: +, - or *.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['+'])
                        ->end()
                        ->scalarNode('session_key')
                            ->info('Session key holding the expected answer.')
                            ->defaultValue('_math_captcha_answer')
                        ->end()
                    ->end()
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
