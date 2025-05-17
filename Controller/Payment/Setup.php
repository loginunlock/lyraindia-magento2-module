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

namespace Lyra\LyraIndia\Controller\Payment;

class Setup extends AbstractLyraIndiaStandard
{
    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $message = '';
        $order = $this->orderInterface->loadByIncrementId($this->checkoutSession->getLastRealOrder()->getIncrementId());
        
        if ($order && $this->method->getCode() == $order->getPayment()->getMethod()) {
            try {
                return $this->processAuthorization($order);
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $this->logger->error("LYRAINDIA_ERROR: " . $message);
                $order->addStatusToHistory($order->getStatus(), $message);
                $this->orderRepository->save($order);
            }
        }

        $this->redirectToFinal(false, $message);
    }

    /**
     * Process authorization for the order
     *
     * @param \Magento\Sales\Model\Order $order
     * @return \Magento\Framework\Controller\ResultInterface
     */
    protected function processAuthorization(\Magento\Sales\Model\Order $order)
    {
        // Get order details
        $amount = $order->getGrandTotal();
        $currency = $order->getOrderCurrencyCode();
        $orderId = $order->getIncrementId();
        
        $returnUrl = $this->configProvider->getStore()->getBaseUrl() . "lyraindia/payment/callback";
        $webhookUrl = $this->configProvider->getStore()->getBaseUrl() . "lyraindia/payment/webhook";
        
        // Prepare LyraIndia request
        $requestData = [
            'amount' => $amount * 100, // Convert to cents
            'currency' => $currency,
            'orderId' => $orderId,
            'customer' => [
                'name' => $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname(),
                'emailId' => $order->getCustomerEmail(),
                'phone' => $order->getBillingAddress()->getTelephone(),
                'reference' => $order->getCustomerId() ?: $order->getCustomerEmail()
            ],
            'webhook' => [
                'url' => $webhookUrl,
            ],
            'returnInfo' => [
                'method' => 'POST',
                'url' => $returnUrl,
                'timeout' => 600,
            ]
        ];

        $jsonPayload = json_encode($requestData, JSON_UNESCAPED_SLASHES);

        $rawHeaders = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->lyraConfig['shopId'] . ':' . $this->lyraConfig['apiKey']),
            'shopId: ' . $this->lyraConfig['shopId'],
            'appId: ' . $this->lyraConfig['appId'],
            'apikey: ' . $this->lyraConfig['apiKey'],
            'Content-Length: ' . strlen($jsonPayload),
            'Expect:' // disables 100-continue
        ];

        // Apply all required cURL options just once
        $this->httpClient->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $this->httpClient->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $this->httpClient->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->httpClient->setOption(CURLOPT_POST, true);
        $this->httpClient->setOption(CURLOPT_TIMEOUT, 60);
        $this->httpClient->setOption(CURLOPT_HTTPHEADER, $rawHeaders);

        // Get the appropriate API URL based on test mode
        $apiUrl = $this->method->getConfigData('test_mode') ? 
            \Lyra\LyraIndia\Model\Payment\LyraIndia::LYRA_TEST_URL : 
            \Lyra\LyraIndia\Model\Payment\LyraIndia::LYRA_LIVE_URL;

        $this->logger->debug('LyraIndia Request URL: ' . $apiUrl);
        $this->logger->debug('LyraIndia Request Payload: ' . $jsonPayload);

        // Make API request
        $this->httpClient->post($apiUrl, $jsonPayload);

        $this->logger->debug('LyraIndia HTTP Status: ' . $this->httpClient->getStatus());
        $this->logger->debug('LyraIndia Response Body: ' . $this->httpClient->getBody());

        $response = json_decode($this->httpClient->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response');
        }

        $this->logger->debug('LyraIndia Response: ' . print_r($response, true));
        
        if (isset($response['uuid'])) {
            // Store transaction ID
            $order->getPayment()->setLastTransId($response['uuid']);
            $order->getPayment()->setTransactionId($response['uuid']);
            $order->getPayment()->setAdditionalInformation('transaction_id', $response['uuid']);
            $order->save();

            // Redirect to payment page
            $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($response['paymentLink']);
            return $resultRedirect;
        }

        throw new \Exception('Failed to create payment: ' . ($response['message'] ?? 'Unknown error'));
    }
}
