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
