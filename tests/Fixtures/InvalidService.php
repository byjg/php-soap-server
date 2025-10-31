<?php

namespace Test\Fixtures;


/**
 * Service without SoapService attribute for testing error handling
 */
class InvalidService
{
    public function someMethod(): void
    {
    }
}
