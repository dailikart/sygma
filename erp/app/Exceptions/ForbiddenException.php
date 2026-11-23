<?php

namespace App\Exceptions;

use CodeIgniter\Exceptions\HTTPExceptionInterface;
use RuntimeException;

/** Shown as a 403 "no access" page. */
class ForbiddenException extends RuntimeException implements HTTPExceptionInterface
{
    public function __construct(string $message = 'You do not have access to this page.')
    {
        parent::__construct($message, 403);
    }
}
