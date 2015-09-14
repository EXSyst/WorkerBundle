<?php

namespace EXSyst\Bundle\WorkerBundle;

use EXSyst\Component\Worker\Internal\IdentificationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class WorkerRegistry implements CacheClearerInterface, CacheWarmerInterface
{
    const IMPLEMENTATION_CLASS = 0;
    const IMPLEMENTATION_EXPRESSION = 1;
    const IMPLEMENTATION_SERVICE = 2;

    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var array
     */
    private $factories;
    /**
     * @var array
     */
    private $sharedWorkers;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->factories = [];
        $this->sharedWorkers = [];
    }

    public function getFactories()
    {
        return $this->factories;
    }

    public function getFactory($name = null)
    {
        if ($name === null) {
            $name = 'default';
        }

        if (!isset($this->factories[$name])) {
            throw new Exception\NoSuchFactoryException('Unknown worker factory');
        }

        return $this->container->get($this->factories[$name]);
    }

    public function registerFactory($name, $factoryService)
    {
        $this->factories[$name] = $factoryService;

        return $this;
    }

    public function getSharedWorkerNames()
    {
        return array_keys($this->sharedWorkers);
    }

    public function getSharedWorkerFactoryName($workerName)
    {
        if (!isset($this->sharedWorkers[$workerName])) {
            throw new Exception\NoSuchSharedWorkerException('Unknown shared worker');
        }

        return $this->sharedWorkers[$workerName][0];
    }

    public function getSharedWorkerFactory($workerName)
    {
        return $this->getFactory($this->getSharedWorkerFactoryName($workerName));
    }

    public function getSharedWorkerSocketAddress($workerName)
    {
        if (!isset($this->sharedWorkers[$workerName])) {
            throw new Exception\NoSuchSharedWorkerException('Unknown shared worker');
        }

        return $this->sharedWorkers[$workerName][1];
    }

    public function connectToSharedWorker($workerName, $autoStart = true)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->connectToSharedWorkerWithExpression($sharedWorker[1], $sharedWorker[2], $autoStart);
    }

    public function startSharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];
        $factory->startSharedWorkerWithExpression($sharedWorker[1], $sharedWorker[2]);

        return $this;
    }

    public function getSharedWorkerProcessId($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->getSharedWorkerProcessId($sharedWorker[1]);
    }

    public function stopSharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->stopSharedWorker($sharedWorker[1]);
    }

    public function querySharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->querySharedWorker($sharedWorker[1]);
    }

    public function disableSharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];
        $factory->disableSharedWorker($sharedWorker[1]);

        return $this;
    }

    public function reEnableSharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];
        $factory->reEnableSharedWorker($sharedWorker[1]);

        return $this;
    }

    public function isSharedWorkerDisabled($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->isSharedWorkerDisabled($sharedWorker[1]);
    }

    public function isSharedWorkerEagerlyStarting($workerName)
    {
        if (!isset($this->sharedWorkers[$workerName])) {
            throw new Exception\NoSuchSharedWorkerException('Unknown shared worker');
        }

        return $this->sharedWorkers[$workerName][3];
    }

    public function registerSharedWorker($workerName, $factoryName, $socketAddress, $implementationExpression, $eagerStart)
    {
        $this->sharedWorkers[$workerName] = [$factoryName, $socketAddress, $implementationExpression, $eagerStart];

        return $this;
    }

    public function clear($cacheDir)
    {
        foreach ($this->sharedWorkers as $sharedWorker) {
            $factory = $this->getFactory($sharedWorker[0]);
            if (IdentificationHelper::isLocalAddress($sharedWorker[1])) {
                $factory->stopSharedWorker($sharedWorker[1]);
            }
        }
    }

    public function warmUp($cacheDir)
    {
        foreach ($this->sharedWorkers as $sharedWorker) {
            $factory = $this->getFactory($sharedWorker[0]);
            $factory->getBootstrapProfile()->compileScriptWithExpression($sharedWorker[2], $sharedWorker[1], $scriptPath, $mustDeleteOnError);
            if ($mustDeleteOnError) {
                unlink($scriptPath);
                continue;
            }
            if (isset($sharedWorker[2]) && $sharedWorker[3]) {
                register_shutdown_function(function () use ($factory, $scriptPath, $cacheDir) {
                    // HACK to start the worker after Symfony will have moved the cache directory
                    $factory->getBootstrapProfile()->getOrFindPhpExecutablePathAndArguments($php, $phpArgs);
                    $line = array_merge([$php], $phpArgs, [str_replace($cacheDir, dirname($cacheDir).DIRECTORY_SEPARATOR.$this->container->getParameter('kernel.environment'), $scriptPath)]);
                    system(implode(' ', array_map('escapeshellarg', $line)).' </dev/null >/dev/null 2>&1 &');
                });
            }
        }
    }

    public function isOptional()
    {
        return true;
    }
}
