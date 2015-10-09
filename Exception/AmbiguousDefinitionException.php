<?php

/*
 * This file is part of the WorkerBundle package.
 *
 * (c) EXSyst
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EXSyst\Bundle\WorkerBundle\Exception;

/**
 * Exception thrown if a factory or a shared worker has an ambiguous definition.
 *
 * @author Ener-Getick <egetick@gmail.com>
 * @author Nicolas "Exter-N" L. <exter-n@exter-n.fr>
 */
class AmbiguousDefinitionException extends InvalidArgumentException
{
}
