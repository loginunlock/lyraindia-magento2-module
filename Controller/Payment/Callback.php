<?php

/**
 * LyraIndia Magento2 Module using \Magento\Payment\Model\Method\AbstractMethod
 * Copyright (C) 2025 Lyra.com
 * 
 * This file is part of Lyra/LyraIndia.
 * 
 * Lyra/LyraIndia is free software => you can redistribute it and/or modify
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
 * along with this program. If not, see <http =>//www.gnu.org/licenses/>.
 */

namespace Lyra\LyraIndia\Controller\Payment;

class Callback extends AbstractLyraIndiaStandard {

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute() {

        $allParam = $this->request->getParams();
        if(array_key_exists('full_response',$allParam)){
            $responseParams = json_decode($allParam['full_response'],true);
        }else{
            $responseParams = $allParam;
        }

        // Log callback data
        $this->logger->debug("LYRAINDIA_CALLABACK_LOG: " . print_r($responseParams, true));
        $message = "Error recording payment";

         // Get transaction reference from request
         $lyraTxnRef = isset($responseParams['vads_charge_uuid']) ? $responseParams['vads_charge_uuid'] : false;
        
        if (!$lyraTxnRef) {
            return $this->redirectToFinal(false, $message);
        }

        // Get order ID from request
        $orderId = isset($responseParams['vads_order_id']) ? $responseParams['vads_order_id'] : 0;
        $orderStatus = isset($responseParams['vads_charge_status']) ? $responseParams['vads_charge_status'] : '';

        if (!$orderId) {
            $message = "Invalid Order ID";
            $this->logger->error($message);
            return $this->redirectToFinal(false, $message);
        }
        
        try {
        
            if ('PAID' == $orderStatus) {
                $order = $this->orderInterface->load($orderId);

                // Check if order is already processed
                if (in_array($order->getStatus(), ['processing', 'complete', 'holded'])) {
                    $message = 'Order status: '.$order->getStatusLabel();

                    $this->logger->debug($message);
                    return $this->redirectToFinal(true, $message);

                }

                $orderTotal = $order->getGrandTotal();
                $orderCurrency = $order->getOrderCurrencyCode();
                $amountPaid = isset($responseParams['vads_amount']) ? $responseParams['vads_amount'] / 100 : 0;
                $amountDue = isset($responseParams['vads_due']) ? $responseParams['vads_due'] / 100 : 0;
                $lyraRef = $responseParams['vads_charge_uuid'];
                $paymentCurrency = isset($responseParams['vads_currency']) ? strtoupper($responseParams['vads_currency']) : '';

                // Check if amount paid is less than order total
                if ($amountPaid < $orderTotal) {
                    $message = "Amount paid ($amountPaid) is less than order total ($orderTotal). Order placed on hold.";
                    $order->setState($this->order::STATE_HOLDED)
                          ->setStatus($this->order::STATE_HOLDED)
                          ->addCommentToStatusHistory($message);
                    
                    $order->save();
                    $this->logger->error($message);

                } else if ($paymentCurrency !== $orderCurrency) { // Check if payment currency matches order currency
                    $message = "Payment currency ($paymentCurrency) differs from order currency ($orderCurrency). Order placed on hold.";
                    $order->setState($this->order::STATE_HOLDED)
                          ->setStatus($this->order::STATE_HOLDED)
                          ->addCommentToStatusHistory($message);
                    
                    $order->save();
                    $this->logger->error($message);
                    
                } else {
                    
                    $message = "Payment via Lyra successful (Transaction ID: $lyraRef)\n";

                    // Process successful payment
                    $order->setState($this->order::STATE_PROCESSING)
                        ->setStatus($this->order::STATE_PROCESSING)
                        ->addCommentToStatusHistory($message);

                    $order->getPayment()->setLastTransId($lyraRef);
                    $order->getPayment()->setTransactionId($lyraRef);
                    $order->getPayment()->setAdditionalInformation('transaction_id', $lyraRef);
                    
                    $order->save();

                    $this->createTransaction($order, ['uuid' => $lyraRef]);

                    // Dispatch payment verification event
                    $this->eventManager->dispatch('lyraindia_payment_verify_after', [
                        "lyra_order" => $order,
                    ]);

                    $this->logger->debug($message);

                    return $this->redirectToFinal(true, $message);

                }
                

            } else {
                $message = 'Payment was declined by Lyra.';
                $order = $this->orderInterface->load($orderId);
                $order->setState($this->order::STATE_CANCELED)
                      ->setStatus($this->order::STATE_CANCELED)
                      ->addCommentToStatusHistory('Payment was declined by Lyra.');
                $order->save();
                $this->logger->error($message);
            }
            
        } catch (Exception $e) {
            $message = $e->getMessage();
        }

        return $this->redirectToFinal(false, $message);
    }

}
