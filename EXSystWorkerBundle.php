<?php

namespace EXSyst\Bundle\WorkerBundle;

use EXSyst\Bundle\WorkerBundle\DependencyInjection\EXSystWorkerExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EXSystWorkerBundle extends Bundle
{
    /** {@inheritdoc} */
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
