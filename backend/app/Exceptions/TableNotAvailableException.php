<?php

namespace App\Exceptions;

use Exception;

class TableNotAvailableException extends Exception
{
    public function __construct(string $tableNumber, string $currentStatus)
    {
        parent::__construct(
            "Meja {$tableNumber} tidak tersedia. Status saat ini: {$currentStatus}.",
            409
        );
    }
}
