<?php

namespace App\Exceptions;

use Exception;

class OrderAlreadyProcessedException extends Exception
{
    public function __construct(string $orderCode)
    {
        parent::__construct("Order {$orderCode} sudah diproses sebelumnya.", 409);
    }
}
