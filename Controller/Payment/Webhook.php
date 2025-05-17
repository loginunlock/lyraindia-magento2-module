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

class Webhook extends AbstractLyraIndiaStandard
{

    public function execute() {
        $finalMessage = "failed";
        
        $resultFactory = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        // Check if request method is POST
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $resultFactory->setContents("Invalid request method");
            return $resultFactory;
        }

        // Get raw request body
        $rawBody = file_get_contents('php://input');
        $event = json_decode($rawBody);
        
        // Log webhook data
        $this->logger->debug("LYRAINDIA_WEBHOOK_LOG: " . print_r($event, true));
        
        // Sleep for 10 seconds to allow for any pending operations
        sleep(10);

        if (!isset($event->uuid) && !empty($event->uuid) && !isset($event->orderId) && !empty($event->orderId)) {
            $resultFactory->setContents($finalMessage);
            return $resultFactory;
        }

        try {
            
            // Get order ID from event
            $orderId = (int) $event->orderId;
            $order = $this->orderInterface->load($orderId);

            if (!$order || !$order->getId()) {
                $resultFactory->setContents("Order not found");
                return $resultFactory;
            }

            // Get transaction ID from order
            $lyraTxnRef = $order->getPayment()->getLastTransId();
            
            // Verify transaction ID matches
            if ($event->uuid != $lyraTxnRef) {
                $resultFactory->setContents("Transaction ID mismatch");
                return $resultFactory;
            }

            // Check if order is already processed
            if (in_array($order->getStatus(), ['processing', 'complete', 'holded'])) {
                $resultFactory->setContents("Order already processed");
                return $resultFactory;
            }

            $orderCurrency = $order->getOrderCurrencyCode();
            $orderTotal = $order->getGrandTotal();
            $amountPaid = $event->amount / 100;
            $lyraRef = $event->uuid;
            $paymentCurrency = strtoupper($event->currency);

            // Check if amount paid is less than order total
            if ($amountPaid < $orderTotal) {
                $order->setState($this->order::STATE_HOLDED)
                      ->setStatus($this->order::STATE_HOLDED)
                      ->addCommentToStatusHistory(
                          "Amount paid ($amountPaid) is less than order total ($orderTotal). Order placed on hold."
                      );
                
                $order->save();
                $resultFactory->setContents("Amount paid less than order total");
                return $resultFactory;
            }

            // Check if payment currency matches order currency
            if ($paymentCurrency !== $orderCurrency) {
                $order->setState($this->order::STATE_HOLDED)
                      ->setStatus($this->order::STATE_HOLDED)
                      ->addCommentToStatusHistory(
                          "Payment currency ($paymentCurrency) differs from order currency ($orderCurrency). Order placed on hold."
                      );
                
                $order->save();
                $resultFactory->setContents("Currency mismatch");
                return $resultFactory;
            }

            // Process successful payment
            $transaction = $event->transactions[0] ?? [];
            $comment = "Payment via Lyra successful (Transaction ID: $lyraRef), \n";

            $transactionData = [];

            if ($transaction->family === 'UPI') {
                $comment .= "Payment Method: UPI, \n" .
                           "Payer VPA: {$transaction->payerVpa}, \n" .
                           "Transaction ID: {$transaction->uuid}";

                $transactionData = [
                    'Paid via' => $transaction->family ?? '',
                    'Payer VPA' => $transaction->payerVpa ?? '',
                    'Transaction ID' => $transaction->uuid ?? '',
                    'uuid' => $event->uuid
                ];
            } elseif ($transaction->family === 'CARD') {
                $comment .= "Payment Method: Card, \n" .
                           "Card Type: {$transaction->cardType}, \n" .
                           "Card Scheme: {$transaction->scheme}, \n" .
                           "Last 4 Digits: {$transaction->cardLast4}, \n" .
                           "Issuing Bank: {$transaction->issuingBank}, \n" .
                           "Auth Number: {$transaction->authNum}, \n" .
                           "Transaction ID: {$transaction->uuid}";

                $transactionData = [
                    'Paid via' => $transaction->family ?? '',
                    'Card Type' => $transaction->cardType ?? '',
                    'Card Scheme' => $transaction->scheme ?? '',
                    'Last 4 Digits' => $transaction->cardLast4 ?? '',
                    'Issuing Bank' => $transaction->issuingBank ?? '',
                    'Auth Number' => $transaction->authNum ?? '',
                    'Transaction ID' => $transaction->uuid ?? '',
                    'uuid' => $event->uuid
                ];
            }
            
            $order->setState($this->order::STATE_PROCESSING)
                  ->setStatus($this->order::STATE_PROCESSING)
                  ->addCommentToStatusHistory($comment);

            $order->getPayment()->setLastTransId($event->uuid);
            $order->getPayment()->setTransactionId($event->uuid);
            $order->getPayment()->setAdditionalInformation('transaction_id', $event->uuid);
            $order->save();

            $this->createTransaction($order, $transactionData);
            
            // Dispatch payment verification event
            $this->eventManager->dispatch('lyraindia_payment_verify_after', [
                "lyraindia_order" => $order,
            ]);

            $resultFactory->setContents("success");
            return $resultFactory;

        } catch (\Exception $exc) {
            $finalMessage = $exc->getMessage();
            $this->logger->error("LYRAINDIA_ERROR: " . $finalMessage);
        }
        
        $resultFactory->setContents($finalMessage);
        return $resultFactory;
    }

}
