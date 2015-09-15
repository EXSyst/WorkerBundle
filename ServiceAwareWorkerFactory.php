<?php

namespace EXSyst\Bundle\WorkerBundle;

use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;
use EXSyst\Component\Worker\WorkerFactory;

class ServiceAwareWorkerFactory extends WorkerFactory
{
    /**
     * @param string $implementationService
     *
     * @return Worker
     */
    public function createWorkerWithService($implementationService)
    {
        return $this->createWorkerWithExpression(self::generateServiceExpression($implementationService));
    }

    /**
     * @param string   $implementationService
     * @param int|null $workerCount
     *
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     *
     * @return WorkerPool
     */
    public function createWorkerPoolWithService($implementationService, $workerCount = null)
    {
        return $this->createWorkerPoolWithExpression(self::generateServiceExpression($implementationService), $workerCount);
    }

    /**
     * @param string      $socketAddress
     * @param string|null $implementationService
     * @param bool        $autoStart
     *
     * @throws Exception\ConnectException
     *
     * @return SharedWorker
     */
    public function connectToSharedWorkerWithService($socketAddress, $implementationService = null, $autoStart = true)
    {
        return $this->connectToSharedWorkerWithExpression($socketAddress, self::generateServiceExpression($implementationService), $autoStart);
    }

    /**
     * @param string $socketAddress
     * @param string $implementationService
     *
     * @throws Exception\ConnectException
     * @throws Exception\LogicException
     *
     * @return $this
     */
    public function startSharedWorkerWithService($socketAddress, $implementationService)
    {
        return $this->startSharedWorkerWithExpression($socketAddress, self::generateServiceExpression($implementationService));
    }

    /**
     * @param string $service
     *
     * @return string
     */
    public static function generateServiceExpression($service)
    {
        return '$kernel->getContainer()->get('.WorkerBootstrapProfile::exportPhpValue($service).')';
    }
}
