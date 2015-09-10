<?php

namespace EXSyst\Bundle\WorkerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;
use EXSyst\Component\Worker\SharedWorker;
use EXSyst\Bundle\WorkerBundle\Exception;
use EXSyst\Bundle\WorkerBundle\ServiceAwareWorkerFactory;
use EXSyst\Bundle\WorkerBundle\WorkerRegistry;

class EXSystWorkerExtension extends Extension
{
    public function getAlias()
    {
        return 'exsyst_worker';
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $registryDefinition = new Definition(WorkerRegistry::class);
        $container->setDefinition('exsyst_worker', $registryDefinition);

        $defaultBootstrapProfileDefinition = $this->createBootstrapProfileDefinition($container, 'default');
        $container->setDefinition('exsyst_worker.bootstrap_profile.default', $defaultBootstrapProfileDefinition);
        $bootstrapProfileDefinitions = [
            'default' => $defaultBootstrapProfileDefinition
        ];
        $bootstrapProfileConstructorArguments = [
            'default' => [ ]
        ];

        $defaultFactoryDefinition = new Definition(ServiceAwareWorkerFactory::class, [ new Reference('exsyst_worker.bootstrap_profile.default') ]);
        $container->setDefinition('exsyst_worker.factory.default', $defaultFactoryDefinition);
        $registryDefinition->addMethodCall('registerFactory', [ 'default', new Reference('service_container'), 'exsyst_worker.factory.default' ]);

        foreach ($config['factories'] as $name => $factoryConfig) {
            if ($name == 'default') {
                $bootstrapProfileDefinition = $defaultBootstrapProfileDefinition;
            } else {
                $bootstrapProfileDefinition = $this->createBootstrapProfileDefinition($container, $name);
                $container->setDefinition('exsyst_worker.bootstrap_profile.' . $name, $bootstrapProfileDefinition);
                $bootstrapProfileDefinitions[$name] = $bootstrapProfileDefinition;
                $bootstrapProfileConstructorArguments[$name] = [ ];

                $defaultFactoryDefinition = new Definition(ServiceAwareWorkerFactory::class, [ new Reference('exsyst_worker.bootstrap_profile.' . $name) ]);
                $container->setDefinition('exsyst_worker.factory.' . $name, $defaultFactoryDefinition);
                $registryDefinition->addMethodCall('registerFactory', [ $name, new Reference('service_container'), 'exsyst_worker.factory.' . $name ]);
            }

            if (isset($factoryConfig['bootstrap_profile'])) {
                $bootstrapProfileConfig = $factoryConfig['bootstrap_profile'];
                if (isset($bootstrapProfileConfig['php']['path'])) {
                    $bootstrapProfileDefinition->addMethodCall('setPhpExecutablePath', [ $bootstrapProfileConfig['php']['path'] ]);
                }
                if (isset($bootstrapProfileConfig['php']['arguments'])) {
                    foreach ($bootstrapProfileConfig['php']['arguments'] as $arg) {
                        $bootstrapProfileDefinition->addMethodCall('addPhpExecutableArgument', [ $arg ]);
                    }
                }
                if (isset($bootstrapProfileConfig['stage1_parts'])) {
                    foreach ($bootstrapProfileConfig['stage1_parts'] as $part) {
                        $bootstrapProfileDefinition->addMethodCall('addStage1Part', [ $part ]);
                    }
                }
                if (isset($bootstrapProfileConfig['scripts_to_require'])) {
                    foreach ($bootstrapProfileConfig['scripts_to_require'] as $script) {
                        $bootstrapProfileDefinition->addMethodCall('addScriptToRequire', [ $script ]);
                    }
                }
                if (isset($bootstrapProfileConfig['stage2_parts'])) {
                    foreach ($bootstrapProfileConfig['stage2_parts'] as $part) {
                        $bootstrapProfileDefinition->addMethodCall('addStage2Part', [ $part ]);
                    }
                }
                if (isset($bootstrapProfileConfig['argument_expressions'])) {
                    foreach ($bootstrapProfileConfig['argument_expressions'] as $arg) {
                        $bootstrapProfileDefinition->addMethodCall('addConstructorArgumentWithExpression', [ $arg ]);
                        $bootstrapProfileConstructorArguments[$name][] = $arg;
                    }
                }
                if (isset($bootstrapProfileConfig['stage3_parts'])) {
                    foreach ($bootstrapProfileConfig['stage3_parts'] as $part) {
                        $bootstrapProfileDefinition->addMethodCall('addStage3Part', [ $part ]);
                    }
                }
                if (isset($bootstrapProfileConfig['channel_factory_service'])) {
                    $bootstrapProfileDefinition->addMethodCall('setChannelFactory', [ new Reference($bootstrapProfileConfig['channel_factory_service']) ]);
                }
                if (isset($bootstrapProfileConfig['loop_expression'])) {
                    if (isset($bootstrapProfileConfig['loop_service'])) {
                        throw new Exception\AmbiguousDefinitionException('Error in worker factory "' . $name . '" : bootstrap profiles can\'t have a loop expression and a loop service at the same time.');
                    }
                    $bootstrapProfileDefinition->addMethodCall('setLoopExpression', [ $bootstrapProfileConfig['loop_expression'] ]);
                } elseif (isset($bootstrapProfileConfig['loop_service'])) {
                    $bootstrapProfileDefinition->addMethodCall('setLoopExpression', [ ServiceAwareWorkerFactory::generateServiceExpression($bootstrapProfileConfig['loop_service']) ]);
                }
                if (isset($bootstrapProfileConfig['socket_context_expression'])) {
                    $bootstrapProfileDefinition->addMethodCall('setSocketContextExpression', [ $bootstrapProfileConfig['socket_context_expression'] ]);
                }
                if (isset($bootstrapProfileConfig['stop_cookie'])) {
                    $bootstrapProfileDefinition->removeMethodCall('setStopCookie');
                    $bootstrapProfileDefinition->addMethodCall('setStopCookie', [ $bootstrapProfileConfig['stop_cookie'] ]);
                }
                if (isset($bootstrapProfileConfig['kill_switch_path'])) {
                    $bootstrapProfileDefinition->removeMethodCall('setKillSwitchPath');
                    $bootstrapProfileDefinition->addMethodCall('setKillSwitchPath', [ $bootstrapProfileConfig['kill_switch_path'] ]);
                }
            }
        }

        foreach ($config['shared_workers'] as $name => $sharedWorkerConfig) {
            $factoryName = isset($sharedWorkerConfig['factory']) ? $sharedWorkerConfig['factory'] : 'default';
            $socketAddress = isset($sharedWorkerConfig['address']) ? $sharedWorkerConfig['address'] : ('unix://' . dirname($container->getParameter('kernel.root_dir')) . DIRECTORY_SEPARATOR . 'run' . DIRECTORY_SEPARATOR . 'exsyst_worker' . DIRECTORY_SEPARATOR . 'shared_worker.' . $name . '.sock');
            if (isset($sharedWorkerConfig['expression'])) {
                if (isset($sharedWorkerConfig['service']) && isset($sharedWorkerConfig['class'])) {
                    throw new Exception\AmbiguousDefinitionException('Error in shared worker "' . $name . '" : shared workers can\'t have an instantiation expression, a service identifier, and a class name at the same time.');
                } elseif (isset($sharedWorkerConfig['service'])) {
                    throw new Exception\AmbiguousDefinitionException('Error in shared worker "' . $name . '" : shared workers can\'t have an instantiation expression and a service identifier at the same time.');
                } elseif (isset($sharedWorkerConfig['class'])) {
                    throw new Exception\AmbiguousDefinitionException('Error in shared worker "' . $name . '" : shared workers can\'t have an instantiation expression and a class name at the same time.');
                }
                $expression = $sharedWorkerConfig['expression'];
            } elseif (isset($sharedWorkerConfig['service'])) {
                if (isset($sharedWorkerConfig['class'])) {
                    throw new Exception\AmbiguousDefinitionException('Error in shared worker "' . $name . '" : shared workers can\'t have a service identifier and a class name at the same time.');
                }
                $expression = ServiceAwareWorkerFactory::generateServiceExpression($sharedWorkerConfig['service']);
            } elseif (isset($sharedWorkerConfig['class'])) {
                $expression = 'new ' . $sharedWorkerConfig['class'] . '(' . implode(', ', $bootstrapProfileConstructorArguments[$factoryName]) . ')';
            } else {
                $expression = null;
            }
            $eagerStart = isset($sharedWorkerConfig['eager_start']) ? !!$sharedWorkerConfig['eager_start'] : false;

            $sharedWorkerDefinition = new Definition(SharedWorker::class, [ $socketAddress, $expression, true ]);
            $sharedWorkerDefinition->setFactory([ new Reference('exsyst_worker.factory.' . $factoryName), 'connectToSharedWorkerWithExpression' ]);
            $container->setDefinition('exsyst_worker.shared_worker.' . $name, $sharedWorkerDefinition);
            $registryDefinition->addMethodCall('registerSharedWorker', [ $name, $factoryName, $socketAddress, $expression, $eagerStart ]);
            if ($expression !== null) {
                $bootstrapProfileDefinitions[$factoryName]->addMethodCall('addPrecompiledScriptWithExpression', [ $expression, dirname($container->getParameter('kernel.cache_dir')) . DIRECTORY_SEPARATOR . 'exsyst_worker' . DIRECTORY_SEPARATOR . 'shared_worker.' . $name . '.php', $socketAddress ]);
            }
        }
    }

    private function createBootstrapProfileDefinition(ContainerBuilder $container, $name)
    {
        $killSwitchPath = dirname($container->getParameter('kernel.root_dir')) . DIRECTORY_SEPARATOR . 'run' . DIRECTORY_SEPARATOR . 'exsyst_worker' . DIRECTORY_SEPARATOR . 'kill_switch.' . $name . '.json';

        $definition = new Definition(WorkerBootstrapProfile::class, [ false ]);

        $definition->addMethodCall('addScriptToRequire', [ $container->getParameter('kernel.root_dir') . '/bootstrap.php.cache' ]);
        $definition->addMethodCall('addScriptToRequire', [ $container->getParameter('kernel.root_dir') . '/AppKernel.php' ]);
        $definition->addMethodCall('addStage2GlobalVariableWithExpression', [ 'kernel',
            'new AppKernel(' . WorkerBootstrapProfile::exportPhpValue($container->getParameter('kernel.environment')) . ', ' . WorkerBootstrapProfile::exportPhpValue($container->getParameter('kernel.debug')) . ')' ]);
        $definition->addMethodCall('addStage2Part', [ '$kernel->loadClassCache();' ]);
        $definition->addMethodCall('addStage3Part', [ 'if ($workerImpl instanceof ' . ContainerAwareInterface::class . ') {' . PHP_EOL . '    $workerImpl->setContainer($kernel->getContainer());' . PHP_EOL . '}' ]);
        $definition->addMethodCall('setStopCookie', [ rtrim(strtr(base64_encode(hash_hmac('sha512', $killSwitchPath, $container->getParameter('kernel.secret'), true)), '+/', '-_'), '=') ]);
        $definition->addMethodCall('setKillSwitchPath', [ $killSwitchPath ]);

        $definition->setPublic(false);

        return $definition;
    }
}
