<?php

namespace Jul6Art\CoreBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder('core');

        $node = $builder->getRootNode();

        $node
            ->children()
                ->scalarNode('email_debug')->defaultFalse()->end()
                ->scalarNode('email_debug_from')->defaultNull()->end()
                ->scalarNode('email_debug_title')->defaultValue('An error occured')->end()
                ->scalarNode('email_debug_to')->defaultNull()->end()
            ->end();

        return $builder;
    }
}
