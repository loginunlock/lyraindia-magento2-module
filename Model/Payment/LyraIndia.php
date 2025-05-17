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

namespace Lyra\LyraIndia\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Payment\Model\Method\Logger as MethodLogger;
use Psr\Log\LoggerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Payment\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Phrase;

/**
 * LyraIndia main payment method model
 * 
 * @author Vinay Jain<hncvj@engineer.com>
 */
class LyraIndia extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'lyra_lyraindia';
    const LYRA_VERSION = '1.0.0';
    const LYRA_TEST_URL = 'https://stagelyrapgmw.securetxns.com:8443/v1/createOrder';
    const LYRA_LIVE_URL = 'https://stagelyrapgmw.securetxns.com:8443/v1/createOrder';
    const LYRA_REFUND_TEST_URL = 'https://stagelyrapgmw.securetxns.com:8443/v1/refundRequest';
    const LYRA_REFUND_LIVE_URL = 'https://stagelyrapgmw.securetxns.com:8443/v1/refundRequest';
    const LYRA_APP_ID = 3;
    
    protected $_code = self::CODE;
    protected $_isOffline = false;
    protected $_app_id = self::LYRA_APP_ID;
    protected $_version = self::LYRA_VERSION;
    protected $_testUrl = self::LYRA_TEST_URL;
    protected $_liveUrl = self::LYRA_LIVE_URL;
    protected $_refundTestUrl = self::LYRA_REFUND_TEST_URL;
    protected $_refundLiveUrl = self::LYRA_REFUND_LIVE_URL;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $curl;
    protected $logger;
    protected $_storeManager;

    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        MethodLogger $methodLogger,
        LoggerInterface $logger,
        Curl $curl,
        StoreManagerInterface $storeManager,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $methodLogger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->logger = $logger;
        $this->curl = $curl;
        $this->_storeManager = $storeManager;
    }

    public function refund(InfoInterface $payment, $amount)
    {
        $this->logger->debug('LyraIndia Refund: Process Initiated');

        $transactionId = $payment->getParentTransactionId();
        $this->logger->debug('LyraIndia Refund: Transaction ID: '.$transactionId);

        if (!$transactionId) {
            $this->logger->error('LyraIndia Refund: No transaction to refund.');
            throw new LocalizedException(new Phrase('No transaction to refund.'));
        }

        $order = $payment->getOrder();
        $orderId = $order->getIncrementId();
        $orderCurrency = $order->getOrderCurrencyCode();

        $url = $this->getConfigData('test_mode') ? self::LYRA_REFUND_TEST_URL : self::LYRA_REFUND_LIVE_URL;
        $shopId = $this->getConfigData('shop_id');
        $apiKey = $this->getConfigData('test_mode') ? $this->getConfigData('test_api_key') : $this->getConfigData('live_api_key');

        $authHeader = 'Basic ' . base64_encode($shopId . ':' . $apiKey);

        $rawHeaders  = [
            'Content-Type' => 'application/json',
            'appId' => self::LYRA_APP_ID,
            'shopId' => $shopId,
            'apikey' => $apiKey,
            'Authorization' => $authHeader,
            'uuid' => (string) $transactionId,
        ];

        $body = [
            'amount' => $amount * 100, // Convert to cents
            'currency' => $orderCurrency,
            'refundRef' => $orderId . time()
        ];

        $this->logger->debug('LyraIndia Refund: Request Headers: ' . print_r($rawHeaders, true));
        $this->logger->debug('LyraIndia Refund: Request Body: ' . print_r($body, true));

        try {
            // Set all curl-level options
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, false);
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_POST, true);
            $this->curl->setOption(CURLOPT_TIMEOUT, 60);

            $formattedHeaders = [];
            foreach ($rawHeaders as $key => $value) {
                $formattedHeaders[] = $key . ': ' . $value;
            }
            $this->curl->setOption(CURLOPT_HTTPHEADER, $formattedHeaders);

            $this->curl->setTimeout(60);
            $this->curl->post($url, json_encode($body));
        
            $responseBody = json_decode($this->curl->getBody(), true);
            $statusCode = $this->curl->getStatus();

            $this->logger->debug('LyraIndia Refund: HTTP Status: ' . $this->curl->getStatus());
            $this->logger->debug('LyraIndia Refund: Response Body: ' . $this->curl->getBody());

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('LyraIndia Refund: Invalid JSON response');
                throw new \Exception('Invalid JSON response');
            }

            $this->logger->debug('LyraIndia Refund: Response: ' . print_r($responseBody, true));
        
            if ($statusCode != 200) {
                $this->logger->error('LyraIndia Refund: Refund API Error: ' . ($responseBody['message'] ?? 'Unknown error'));
                throw new LocalizedException(new Phrase('Error: ' . ($responseBody['message'] ?? 'Unknown error')));
            }

            // Add order note with refund details
            $refundMessage = new Phrase(
                'Refund request %1 for %2. Refund ID: %3',
                [
                    $responseBody['status'],
                    $this->formatPrice($amount, $orderCurrency),
                    $responseBody['chargeUuid']
                ]
            );
            $order->addStatusHistoryComment($refundMessage);

            // Set refund transaction ID and mark it closed
            $payment->setTransactionId($transactionId . '-refund');
            $payment->setIsTransactionClosed(true);

            return $responseBody['status'] === 'ACCEPTED';
        } catch (\Exception $e) {
            $this->logger->error('LyraIndia Refund: Refund failed: ' . $e->getMessage());
            throw new LocalizedException(new Phrase('Refund failed: ' . $e->getMessage()));
        }
    }

    /**
     * Format price with currency
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    private function formatPrice($amount, $currency)
    {
        return $this->_storeManager->getStore()->getBaseCurrency()->formatPrecision(
            $amount,
            2,
            [],
            false
        );
    }

    /**
     * Override to add min/max order total validation.
     *
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(?CartInterface $quote = null)
    {
        if (!$quote) {
            return parent::isAvailable($quote);
        }
    
        $orderTotal = $quote->getBaseGrandTotal();
    
        // Get min/max values from config
        $min = $this->getConfigData('min_order_total');
        $max = $this->getConfigData('max_order_total');
    
        // Cast to float just in case they're stored as strings
        $min = (float) $min;
        $max = (float) $max;
    
        // Validate total
        if (($min && $orderTotal < $min) || ($max && $orderTotal > $max)) {
            return false;
        }
    
        return parent::isAvailable($quote);
    }
}
