<?php

namespace Barzahlen\Components\Payment;

class PaymentResponse
{
    /**
     * @var int
     */
    public $transactionId;

    /**
     * @var string
     */
    public $token;

    /**
     * @var string
     */
    public $status;
}
