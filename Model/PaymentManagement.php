<?php
/**
 * LyraIndia Magento2 Module using \Magento\Payment\Model\Method\AbstractMethod
 * Copyright (C) 2025 Lyra.com
 * 
 * This file is part of Lyra/LyraIndia.
 * 
 * Lyra/LyraIndia is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Lyra\LyraIndia\Model;

use Exception;
use Magento\Payment\Helper\Data as PaymentHelper;
use Lyra\LyraIndia\Model\Payment\LyraIndia as LyraIndiaModel;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class PaymentManagement implements \Lyra\LyraIndia\Api\PaymentManagementInterface
{
    protected $lyraPaymentInstance;
    protected $orderInterface;
    protected $checkoutSession;
    protected $eventManager;
    protected $logger;

    public function __construct(
        PaymentHelper $paymentHelper,
        ManagerInterface $eventManager,
        OrderInterface $orderInterface,
        Session $checkoutSession,
        LoggerInterface $logger
    ) {
        $this->eventManager = $eventManager;
        $this->lyraPaymentInstance = $paymentHelper->getMethodInstance(LyraIndiaModel::CODE);
        $this->orderInterface = $orderInterface;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * @param string $reference
     * @return bool
     */
    public function verifyPayment($reference)
    {
        $this->logger->debug('Transaction Verification');
        $this->logger->debug('Request received: ' . print_r($_REQUEST, true));

        // Get transaction reference from request
        $lyraTxnRef = isset($_REQUEST['vads_charge_uuid']) ? $_REQUEST['vads_charge_uuid'] : false;
        
        // Get order ID from request
        $orderId = isset($_REQUEST['vads_order_id']) ? (int)$_REQUEST['vads_order_id'] : 0;
        $orderStatus = isset($_REQUEST['vads_charge_status']) ? $_REQUEST['vads_charge_status'] : '';

        if (!$orderId) {
            $this->logger->error('Order ID missing');
            return json_encode([
                'status' => 0,
                'message' => 'Order ID missing'
            ]);
        }

        if ($lyraTxnRef) {
            if ('PAID' == $orderStatus) {
                $order = $this->orderInterface->load($orderId);

                // Check if order is already processed
                if (in_array($order->getStatus(), ['processing', 'complete', 'holded'])) {
                    return json_encode([
                        'status' => 1,
                        'message' => 'Order already processed'
                    ]);
                }

                $orderTotal = $order->getGrandTotal();
                $orderCurrency = $order->getOrderCurrencyCode();
                $amountPaid = isset($_REQUEST['vads_amount']) ? $_REQUEST['vads_amount'] / 100 : 0;
                $amountDue = isset($_REQUEST['vads_due']) ? $_REQUEST['vads_due'] / 100 : 0;
                $lyraRef = $_REQUEST['vads_charge_uuid'];
                $paymentCurrency = isset($_REQUEST['vads_currency']) ? strtoupper($_REQUEST['vads_currency']) : '';

                // Check if amount paid is less than order total
                if ($amountPaid < $orderTotal) {
                    $order->setState(Order::STATE_HOLDED)
                          ->setStatus(Order::STATE_HOLDED)
                          ->addCommentToStatusHistory(
                              "Amount paid ($amountPaid) is less than order total ($orderTotal). Order placed on hold."
                          );
                    
                    $order->save();
                    return json_encode([
                        'status' => 0,
                        'message' => 'Amount paid less than order total'
                    ]);
                }

                // Check if payment currency matches order currency
                if ($paymentCurrency !== $orderCurrency) {
                    $order->setState(Order::STATE_HOLDED)
                          ->setStatus(Order::STATE_HOLDED)
                          ->addCommentToStatusHistory(
                              "Payment currency ($paymentCurrency) differs from order currency ($orderCurrency). Order placed on hold."
                          );
                    
                    $order->save();
                    return json_encode([
                        'status' => 0,
                        'message' => 'Currency mismatch'
                    ]);
                }

                // Process successful payment
                $order->setState(Order::STATE_PROCESSING)
                      ->setStatus(Order::STATE_PROCESSING)
                      ->addCommentToStatusHistory("Payment via Lyra successful (Transaction ID: $lyraRef)");
                
                $order->save();

                // Dispatch payment verification event
                $this->eventManager->dispatch('lyraindia_payment_verify_after', [
                    "lyra_order" => $order,
                ]);

                return json_encode([
                    'status' => 1,
                    'message' => 'Payment successful'
                ]);
            } else {
                $order = $this->orderInterface->load($orderId);
                $order->setState(Order::STATE_CANCELED)
                      ->setStatus(Order::STATE_CANCELED)
                      ->addCommentToStatusHistory('Payment was declined by Lyra.');
                $order->save();

                return json_encode([
                    'status' => 0,
                    'message' => 'Payment declined'
                ]);
            }
        }

        return json_encode([
            'status' => 0,
            'message' => 'Invalid transaction reference'
        ]);
    }

    /**
     * Loads the order based on the last real order
     * @return boolean
     */
    private function getOrder()
    {
        // get the last real order id
        $lastOrder = $this->checkoutSession->getLastRealOrder();
        if($lastOrder){
            $lastOrderId = $lastOrder->getIncrementId();
        } else {
            return false;
        }
        
        if ($lastOrderId) {
            // load and return the order instance
            return $this->orderInterface->loadByIncrementId($lastOrderId);
        }
        return false;
    }
}
