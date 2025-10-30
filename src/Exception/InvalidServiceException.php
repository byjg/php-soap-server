<?php

declare(strict_types=1);

namespace ByJG\SoapServer\Exception;

use Exception;

/**
 * Exception thrown when a class is not properly configured as a SOAP service
 */
class InvalidServiceException extends Exception
{
}
