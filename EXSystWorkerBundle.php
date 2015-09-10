<?php

namespace EXSyst\Bundle\WorkerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use EXSyst\Bundle\WorkerBundle\DependencyInjection\EXSystWorkerExtension;

class EXSystWorkerBundle extends Bundle
{
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new EXSystWorkerExtension();
        }
        if ($this->extension) {
            return $this->extension;
        }
    }
}
