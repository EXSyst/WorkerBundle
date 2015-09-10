<?php

namespace EXSyst\Bundle\WorkerBundle\Exception;

/**
 * Exception thrown if a value does not adhere to a defined valid data domain.
 *
 * @author Ener-Getick <egetick@gmail.com>
 * @author Nicolas "Exter-N" L. <exter-n@exter-n.fr>
 */
class DomainException extends \DomainException implements ExceptionInterface
{
}
