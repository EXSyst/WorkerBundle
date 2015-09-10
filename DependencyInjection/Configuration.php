<?php

namespace EXSyst\Bundle\WorkerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('exsyst_worker');

        $rootNode
            ->children()
                ->arrayNode('factories')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('bootstrap_profile')
                                ->children()
                                    ->arrayNode('php')
                                        ->children()
                                            ->scalarNode('path')->end()
                                            ->arrayNode('arguments')->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('stage1_parts')->end()
                                    ->arrayNode('scripts_to_require')->end()
                                    ->arrayNode('stage2_parts')->end()
                                    ->arrayNode('argument_expressions')->end()
                                    ->arrayNode('stage3_parts')->end()
                                    ->scalarNode('channel_factory_service')->end()
                                    ->scalarNode('loop_expression')->end()
                                    ->scalarNode('loop_service')->end()
                                    ->scalarNode('socket_context_expression')->end()
                                    ->scalarNode('stop_cookie')->end()
                                    ->scalarNode('kill_switch_path')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('shared_workers')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('factory')->end()
                            ->scalarNode('address')->end()
                            ->scalarNode('class')->end()
                            ->scalarNode('service')->end()
                            ->scalarNode('expression')->end()
                            ->booleanNode('eager_start')
                                ->defaultFalse()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
