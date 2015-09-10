<?php

namespace EXSyst\Bundle\WorkerBundle;

use EXSyst\Component\Worker\WorkerFactory;
use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;

class ServiceAwareWorkerFactory extends WorkerFactory
{
    public function createWorkerWithService($implementationService)
    {
        return $this->createWorkerWithExpression(self::generateServiceExpression($implementationService));
    }

    public function createWorkerPoolWithService($implementationService, $workerCount)
    {
        return $this->createWorkerPoolWithExpression(self::generateServiceExpression($implementationService), $workerCount);
    }

    public function connectToSharedWorkerWithService($socketAddress, $implementationService = null, $autoStart = true)
    {
        return $this->connectToSharedWorkerWithExpression($socketAddress, self::generateServiceExpression($implementationService), $autoStart);
    }

    public function startSharedWorkerWithService($socketAddress, $implementationService)
    {
        return $this->startSharedWorkerWithExpression($socketAddress, self::generateServiceExpression($implementationService));
    }

    public static function generateServiceExpression($service)
    {
        return '$kernel->getContainer()->get('.WorkerBootstrapProfile::exportPhpValue($service).')';
    }
}
