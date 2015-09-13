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
        $registryDefinition->addTag('kernel.cache_clearer');
        $registryDefinition->addTag('kernel.cache_warmer', [ 'priority' => -10 ]);
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
                self::processBootstrapProfileConfiguration($factoryConfig['bootstrap_profile'], $name, $bootstrapProfileDefinition, $bootstrapProfileConstructorArguments);
            }
        }

        foreach ($config['shared_workers'] as $name => $sharedWorkerConfig) {
            $factoryName = isset($sharedWorkerConfig['factory']) ? $sharedWorkerConfig['factory'] : 'default';
            $socketAddress = isset($sharedWorkerConfig['address']) ? $sharedWorkerConfig['address'] : ('unix://' . dirname($container->getParameter('kernel.root_dir')) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'exsyst_worker' . DIRECTORY_SEPARATOR . 'shared_worker.' . $name . '.sock');
            $expression = self::getSharedWorkerExpression($sharedWorkerConfig, $name, $bootstrapProfileConstructorArguments[$factoryName]);
            $eagerStart = isset($sharedWorkerConfig['eager_start']) ? !!$sharedWorkerConfig['eager_start'] : false;

            $sharedWorkerDefinition = new Definition(SharedWorker::class, [ $socketAddress, $expression, true ]);
            $sharedWorkerDefinition->setFactory([ new Reference('exsyst_worker.factory.' . $factoryName), 'connectToSharedWorkerWithExpression' ]);
            $container->setDefinition('exsyst_worker.shared_worker.' . $name, $sharedWorkerDefinition);
            $registryDefinition->addMethodCall('registerSharedWorker', [ $name, $factoryName, $socketAddress, $expression, $eagerStart ]);
            if ($expression !== null) {
                $bootstrapProfileDefinitions[$factoryName]->addMethodCall('addPrecompiledScriptWithExpression', [ $expression, $container->getParameter('kernel.cache_dir') . DIRECTORY_SEPARATOR . 'exsyst_worker' . DIRECTORY_SEPARATOR . 'shared_worker.' . $name . '.php', $socketAddress ]);
            }
        }
    }

    private function createBootstrapProfileDefinition(ContainerBuilder $container, $name)
    {
        $killSwitchPath = dirname($container->getParameter('kernel.root_dir')) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'exsyst_worker' . DIRECTORY_SEPARATOR . 'kill_switch.' . $name . '.json';

        $definition = new Definition(WorkerBootstrapProfile::class, [ false ]);

        $definition->addMethodCall('addScriptToRequire', [ dirname($container->getParameter('kernel.logs_dir')) . '/bootstrap.php.cache' ]);
        $definition->addMethodCall('addScriptToRequire', [ $container->getParameter('kernel.root_dir') . '/AppKernel.php' ]);
        $definition->addMethodCall('addStage2GlobalVariableWithExpression', [ 'kernel',
            'new AppKernel(' . WorkerBootstrapProfile::exportPhpValue($container->getParameter('kernel.environment')) . ', ' . WorkerBootstrapProfile::exportPhpValue($container->getParameter('kernel.debug')) . ')' ]);
        $definition->addMethodCall('addStage2Part', [ '$kernel->loadClassCache();' ]);
        $definition->addMethodCall('addStage3Part', [ 'if ($workerImpl instanceof ' . ContainerAwareInterface::class . ') {' . PHP_EOL . '    $workerImpl->setContainer($kernel->getContainer());' . PHP_EOL . '}' ]);
        $definition->addMethodCall('setAdminCookie', [ rtrim(strtr(base64_encode(hash_hmac('sha512', $killSwitchPath, $container->getParameter('kernel.secret'), true)), '+/', '-_'), '=') ]);
        $definition->addMethodCall('setKillSwitchPath', [ $killSwitchPath ]);

        $definition->setPublic(false);

        return $definition;
    }

    private static function processBootstrapProfileConfiguration(array $bootstrapProfileConfig, $name, Definition $bootstrapProfileDefinition, array &$bootstrapProfileConstructorArguments)
    {
        self::processBootstrapProfilePhpConfiguration($bootstrapProfileConfig, $bootstrapProfileDefinition);
        self::processBootstrapProfileConfigurationArrayElement($bootstrapProfileConfig, 'stage1_parts', $bootstrapProfileDefinition, 'addStage1Part');
        self::processBootstrapProfileConfigurationArrayElement($bootstrapProfileConfig, 'scripts_to_require', $bootstrapProfileDefinition, 'addScriptToRequire');
        self::processBootstrapProfileConfigurationArrayElement($bootstrapProfileConfig, 'stage2_parts', $bootstrapProfileDefinition, 'addStage2Part');
        if (isset($bootstrapProfileConfig['argument_expressions'])) {
            foreach ($bootstrapProfileConfig['argument_expressions'] as $arg) {
                $bootstrapProfileDefinition->addMethodCall('addConstructorArgumentWithExpression', [ $arg ]);
                $bootstrapProfileConstructorArguments[$name][] = $arg;
            }
        }
        self::processBootstrapProfileConfigurationArrayElement($bootstrapProfileConfig, 'stage3_parts', $bootstrapProfileDefinition, 'addStage3Part');
        if (isset($bootstrapProfileConfig['channel_factory_service'])) {
            $bootstrapProfileDefinition->addMethodCall('setChannelFactory', [ new Reference($bootstrapProfileConfig['channel_factory_service']) ]);
        }
        self::processBootstrapProfileLoopConfiguration($bootstrapProfileConfig, $bootstrapProfileDefinition, $name);
        if (isset($bootstrapProfileConfig['socket_context_expression'])) {
            $bootstrapProfileDefinition->addMethodCall('setSocketContextExpression', [ $bootstrapProfileConfig['socket_context_expression'] ]);
        }
        self::processBootstrapProfileConfigurationScalarReplacementElement($bootstrapProfileConfig, 'admin_cookie', $bootstrapProfileDefinition, 'setAdminCookie');
        self::processBootstrapProfileConfigurationScalarReplacementElement($bootstrapProfileConfig, 'kill_switch_path', $bootstrapProfileDefinition, 'setKillSwitchPath');
    }
    private static function processBootstrapProfilePhpConfiguration(array $bootstrapProfileConfig, Definition $bootstrapProfileDefinition)
    {
        if (isset($bootstrapProfileConfig['php']['path'])) {
            $bootstrapProfileDefinition->addMethodCall('setPhpExecutablePath', [ $bootstrapProfileConfig['php']['path'] ]);
        }
        if (isset($bootstrapProfileConfig['php']['arguments'])) {
            foreach ($bootstrapProfileConfig['php']['arguments'] as $arg) {
                $bootstrapProfileDefinition->addMethodCall('addPhpExecutableArgument', [ $arg ]);
            }
        }
    }
    private static function processBootstrapProfileConfigurationArrayElement(array $bootstrapProfileConfig, $key, Definition $bootstrapProfileDefinition, $method)
    {
        if (isset($bootstrapProfileConfig[$key])) {
            foreach ($bootstrapProfileConfig[$key] as $element) {
                $bootstrapProfileDefinition->addMethodCall($method, [ $element ]);
            }
        }
    }
    private static function processBootstrapProfileLoopConfiguration(array $bootstrapProfileConfig, Definition $bootstrapProfileDefinition, $name)
    {
        if (isset($bootstrapProfileConfig['loop_expression'])) {
            if (isset($bootstrapProfileConfig['loop_service'])) {
                throw new Exception\AmbiguousDefinitionException('Error in worker factory "' . $name . '" : bootstrap profiles can\'t have a loop expression and a loop service at the same time.');
            }
            $bootstrapProfileDefinition->addMethodCall('setLoopExpression', [ $bootstrapProfileConfig['loop_expression'] ]);
        } elseif (isset($bootstrapProfileConfig['loop_service'])) {
            $bootstrapProfileDefinition->addMethodCall('setLoopExpression', [ ServiceAwareWorkerFactory::generateServiceExpression($bootstrapProfileConfig['loop_service']) ]);
        }
    }
    private static function processBootstrapProfileConfigurationScalarReplacementElement(array $bootstrapProfileConfig, $key, Definition $bootstrapProfileDefinition, $method)
    {
        if (isset($bootstrapProfileConfig[$key])) {
            $bootstrapProfileDefinition->removeMethodCall($method);
            $bootstrapProfileDefinition->addMethodCall($method, [ $bootstrapProfileConfig[$key] ]);
        }
    }

    private static function getSharedWorkerExpression(array $sharedWorkerConfig, $name, array $constructorArguments)
    {
        if (isset($sharedWorkerConfig['expression'])) {
            if (isset($sharedWorkerConfig['service']) && isset($sharedWorkerConfig['class'])) {
                throw new Exception\AmbiguousDefinitionException('Error in shared worker "' . $name . '" : shared workers can\'t have an instantiation expression, a service identifier, and a class name at the same time.');
            } elseif (isset($sharedWorkerConfig['service'])) {
                throw new Exception\AmbiguousDefinitionException('Error in shared worker "' . $name . '" : shared workers can\'t have an instantiation expression and a service identifier at the same time.');
            } elseif (isset($sharedWorkerConfig['class'])) {
                throw new Exception\AmbiguousDefinitionException('Error in shared worker "' . $name . '" : shared workers can\'t have an instantiation expression and a class name at the same time.');
            }
            return $sharedWorkerConfig['expression'];
        } elseif (isset($sharedWorkerConfig['service'])) {
            if (isset($sharedWorkerConfig['class'])) {
                throw new Exception\AmbiguousDefinitionException('Error in shared worker "' . $name . '" : shared workers can\'t have a service identifier and a class name at the same time.');
            }
            return ServiceAwareWorkerFactory::generateServiceExpression($sharedWorkerConfig['service']);
        } elseif (isset($sharedWorkerConfig['class'])) {
            return 'new ' . $sharedWorkerConfig['class'] . '(' . implode(', ', $constructorArguments) . ')';
        }
    }
}
