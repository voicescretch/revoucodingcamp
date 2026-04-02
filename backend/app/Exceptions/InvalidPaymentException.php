<?php

namespace App\Exceptions;

use Exception;

class InvalidPaymentException extends Exception
{
    public function __construct(float $paidAmount, float $totalAmount)
    {
        parent::__construct(
            "Jumlah pembayaran ({$paidAmount}) kurang dari total tagihan ({$totalAmount}).",
            422
        );
    }
}
