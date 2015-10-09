<?php

/*
 * This file is part of the WorkerBundle package.
 *
 * (c) EXSyst
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EXSyst\Bundle\WorkerBundle;

use EXSyst\Component\Worker\Exception\RuntimeException;
use EXSyst\Component\Worker\Internal\IdentificationHelper;
use EXSyst\Component\Worker\SharedWorker;
use EXSyst\Component\Worker\Status\WorkerStatus;
use EXSyst\Component\Worker\WorkerFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class WorkerRegistry implements CacheClearerInterface, CacheWarmerInterface
{
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

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->factories = [];
        $this->sharedWorkers = [];
    }

    /**
     * @return array
     */
    public function getFactoryNames()
    {
        return $this->factories;
    }

    /**
     * @param string|null $name
     *
     * @throws Exception\NoSuchFactoryException
     *
     * @return WorkerFactory
     */
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

    /**
     * @param string $name
     * @param string $factoryService
     *
     * @return $this
     */
    public function registerFactory($name, $factoryService)
    {
        $this->factories[$name] = $factoryService;

        return $this;
    }

    /**
     * @return array
     */
    public function getSharedWorkerNames()
    {
        return array_keys($this->sharedWorkers);
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return string
     */
    public function getSharedWorkerFactoryName($workerName)
    {
        if (!isset($this->sharedWorkers[$workerName])) {
            throw new Exception\NoSuchSharedWorkerException('Unknown shared worker');
        }

        return $this->sharedWorkers[$workerName][0];
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchFactoryException
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return WorkerFactory
     */
    public function getSharedWorkerFactory($workerName)
    {
        return $this->getFactory($this->getSharedWorkerFactoryName($workerName));
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return string
     */
    public function getSharedWorkerSocketAddress($workerName)
    {
        if (!isset($this->sharedWorkers[$workerName])) {
            throw new Exception\NoSuchSharedWorkerException('Unknown shared worker');
        }

        return $this->sharedWorkers[$workerName][1];
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchFactoryException
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return SharedWorker
     */
    public function connectToSharedWorker($workerName, $autoStart = true)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->connectToSharedWorkerWithExpression($sharedWorker[1], $sharedWorker[2], $autoStart);
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchFactoryException
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return $this
     */
    public function startSharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];
        $factory->startSharedWorkerWithExpression($sharedWorker[1], $sharedWorker[2]);

        return $this;
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchFactoryException
     * @throws Exception\NoSuchSharedWorkerException
     * @throws RuntimeException
     *
     * @return int|null
     */
    public function getSharedWorkerProcessId($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->getSharedWorkerProcessId($sharedWorker[1]);
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchFactoryException
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return bool
     */
    public function stopSharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->stopSharedWorker($sharedWorker[1]);
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchFactoryException
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return WorkerStatus
     */
    public function querySharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->querySharedWorker($sharedWorker[1]);
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchFactoryException
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return $this
     */
    public function disableSharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];
        $factory->disableSharedWorker($sharedWorker[1]);

        return $this;
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchFactoryException
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return $this
     */
    public function reEnableSharedWorker($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];
        $factory->reEnableSharedWorker($sharedWorker[1]);

        return $this;
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchFactoryException
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return bool
     */
    public function isSharedWorkerDisabled($workerName)
    {
        $factory = $this->getSharedWorkerFactory($workerName);
        $sharedWorker = $this->sharedWorkers[$workerName];

        return $factory->isSharedWorkerDisabled($sharedWorker[1]);
    }

    /**
     * @param string $workerName
     *
     * @throws Exception\NoSuchSharedWorkerException
     *
     * @return bool
     */
    public function isSharedWorkerEagerlyStarting($workerName)
    {
        if (!isset($this->sharedWorkers[$workerName])) {
            throw new Exception\NoSuchSharedWorkerException('Unknown shared worker');
        }

        return $this->sharedWorkers[$workerName][3];
    }

    /**
     * @param string      $workerName
     * @param string      $factoryName
     * @param string      $socketAddress
     * @param string|null $implementationExpression
     * @param bool        $eagerStart
     */
    public function registerSharedWorker($workerName, $factoryName, $socketAddress, $implementationExpression, $eagerStart)
    {
        $this->sharedWorkers[$workerName] = [$factoryName, $socketAddress, $implementationExpression, $eagerStart];

        return $this;
    }

    /** {@inheritdoc} */
    public function clear($cacheDir)
    {
        foreach ($this->sharedWorkers as $sharedWorker) {
            $factory = $this->getFactory($sharedWorker[0]);
            if (IdentificationHelper::isLocalAddress($sharedWorker[1])) {
                $factory->stopSharedWorker($sharedWorker[1]);
            }
        }
    }

    /** {@inheritdoc} */
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
                    SharedWorker::startDaemon($line);
                });
            }
        }
    }

    /** {@inheritdoc} */
    public function isOptional()
    {
        return true;
    }
}
