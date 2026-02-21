<?php

namespace App\Exceptions;

use Exception;

class UnitAlreadyReservedException extends Exception
{
    protected $message = 'Unit already reserved';
    protected $code = 409;
}
