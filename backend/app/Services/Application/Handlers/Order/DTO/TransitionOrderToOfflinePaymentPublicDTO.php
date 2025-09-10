<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Enums\PaymentProviders; // Import the PaymentProviders enum

class TransitionOrderToOfflinePaymentPublicDTO extends BaseDTO
{
    public function __construct(
        public readonly string $orderShortId,
        public readonly PaymentProviders $paymentProvider, // Add this new property
    ) {
    }
}