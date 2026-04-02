<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(private array $insufficientItems)
    {
        parent::__construct('Stok tidak mencukupi untuk beberapa item.', 422);
    }

    public function getInsufficientItems(): array
    {
        return $this->insufficientItems;
    }
}
