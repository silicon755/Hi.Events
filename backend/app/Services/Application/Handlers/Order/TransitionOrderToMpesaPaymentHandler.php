<?php

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\TransitionOrderToMpesaPaymentPublicDTO;
use HiEvents\Services\Infrastructure\Payments\Mpesa\MpesaPaymentService;
use Illuminate\Database\DatabaseManager;

class TransitionOrderToMpesaPaymentHandler
{
    public function __construct(
        private readonly MpesaPaymentService    $mpesaPaymentService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly DatabaseManager        $databaseManager,
    ) {
    }

    public function handle(TransitionOrderToMpesaPaymentPublicDTO $dto): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($dto) {
            /** @var OrderDomainObject $order */
            $order = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->findByShortId($dto->orderShortId);
            
            // Validate the order state before proceeding
            // Add a new check for isOrderReserved() to ensure the order is in the correct state
            if (!$order->isOrderReserved()) {
                throw new ResourceConflictException(__('Order is not in the correct status to proceed with Mpesa payment'));
            }

            // Initiate the STK Push to the customer's phone
            $success = $this->mpesaPaymentService->initiateStkPush($order, $dto->phoneNumber);

            if (!$success) {
                throw new ResourceConflictException(__('Failed to initiate Mpesa payment. Please try again.'));
            }

            // Update order status to a new state indicating it is awaiting payment
            $this->updateOrderStatuses($order->getId());

            $updatedOrder = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->findById($order->getId());

            // Dispatch events similar to the offline payment handler
            // e.g., an event for the UI to update or a notification to the user

            return $updatedOrder;
        });
    }

    private function updateOrderStatuses(int $orderId): void
    {
        $this->orderRepository->updateFromArray($orderId, [
            OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::AWAITING_PAYMENT->name,
            OrderDomainObjectAbstract::STATUS => OrderStatus::AWAITING_MPESA_PAYMENT->name,
            OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::MPESA->value,
        ]);
    }
}