<?php

namespace HiEvents\Services\Infrastructure\Payments\Mpesa;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\MarkOrderAsPaidService;
use Illuminate\Database\DatabaseManager;

class MpesaCallbackHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DatabaseManager        $databaseManager,
        private readonly MarkOrderAsPaidService $markOrderAsPaidService,
    ) {
    }

    public function handleCallback(array $callbackData): void
    {
        $this->databaseManager->transaction(function () use ($callbackData) {
            $resultCode = data_get($callbackData, 'Body.stkCallback.ResultCode');
            $checkoutRequestId = data_get($callbackData, 'Body.stkCallback.CheckoutRequestID');
            $amount = data_get($callbackData, 'Body.stkCallback.CallbackMetadata.Item.1.Value');
            $mpesaReceiptNumber = data_get($callbackData, 'Body.stkCallback.CallbackMetadata.Item.3.Value');

            if ($resultCode === 0) {
                // Payment was successful
                $order = $this->orderRepository->findFirstWhere([
                    'checkout_request_id' => $checkoutRequestId, 
                    'status' => OrderStatus::AWAITING_MPESA_PAYMENT->name,
                ]);

                if ($order && $order->getTotalGross() == $amount) {
                    $this->markOrderAsPaidService->markOrderAsPaid(
                        orderId: $order->getId(),
                        eventId: $order->getEventId(),
                    );
                    // You might also want to store the mpesaReceiptNumber on the order or invoice
                }
            } else {
                // Payment failed or was cancelled by the user
                $order = $this->orderRepository->findFirstWhere([
                    'checkout_request_id' => $checkoutRequestId,
                    'status' => OrderStatus::AWAITING_MPESA_PAYMENT->name,
                ]);

                if ($order) {
                    $this->orderRepository->updateFromArray($order->getId(), [
                        'status' => OrderStatus::CANCELLED->name, 
                        'payment_status' => OrderPaymentStatus::PAYMENT_FAILED->name,
                    ]);
                }
            }
        });
    }
}