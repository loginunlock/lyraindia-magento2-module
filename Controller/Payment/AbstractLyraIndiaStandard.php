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

use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;


abstract class AbstractLyraIndiaStandard extends \Magento\Framework\App\Action\Action {

    protected $resultPageFactory;

    protected $_transactionBuilder;

    /**
     *
     * @var \Magento\Framework\HTTP\Client\Curl 
     */
    protected $httpClient;
    
    /**
     *
     * @var \Magento\Sales\Api\OrderRepositoryInterface 
     */
    protected $orderRepository;
    
    /**
     *
     * @var \Magento\Sales\Api\Data\OrderInterface
     */
    protected $orderInterface;
    protected $checkoutSession;
    protected $method;
    protected $messageManager;
    protected $order;
    
    /**
     *
     * @var \Lyra\LyraIndia\Model\Ui\ConfigProvider 
     */
    protected $configProvider;
    
    /**
     * @var \Magento\Framework\Event\Manager
     */
    protected $eventManager;
    
    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    
    /**
     *
     * @var \Magento\Framework\App\Request\Http 
     */
    protected $request;

    /**
     * @var array
     */
    protected $lyraConfig;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Api\Data\OrderInterface $orderInterface
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param PaymentHelper $paymentHelper
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Lyra\LyraIndia\Model\Ui\ConfigProvider $configProvider
     * @param \Magento\Framework\Event\Manager $eventManager
     * @param \Magento\Framework\App\Request\Http $request
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\HTTP\Client\Curl $httpClient
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Api\Data\OrderInterface $orderInterface,
        \Magento\Sales\Model\Order $order,
        \Magento\Checkout\Model\Session $checkoutSession,
        PaymentHelper $paymentHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Lyra\LyraIndia\Model\Ui\ConfigProvider $configProvider,
        \Magento\Framework\Event\Manager $eventManager,
        \Magento\Framework\App\Request\Http $request,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\HTTP\Client\Curl $httpClient,
        BuilderInterface $transactionBuilder,
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->orderRepository = $orderRepository;
        $this->orderInterface = $orderInterface;
        $this->checkoutSession = $checkoutSession;
        $this->method = $paymentHelper->getMethodInstance(\Lyra\LyraIndia\Model\Payment\LyraIndia::CODE);
        $this->messageManager = $messageManager;
        $this->configProvider = $configProvider;
        $this->eventManager = $eventManager;
        $this->request = $request;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->order = $order;
        
        // Initialize LyraIndia configuration
        $this->lyraConfig = $this->initLyraIndia();

        $this->_transactionBuilder = $transactionBuilder;
        
        parent::__construct($context);
    }
    
    /**
     * Initialize LyraIndia configuration
     *
     * @return array
     */
    protected function initLyraIndia()
    {
        $merchantId = $this->method->getConfigData('merchant_id');
        $shopId = $this->method->getConfigData('shop_id');
        $apiKey = $this->method->getConfigData('live_api_key');
        if ($this->method->getConfigData('test_mode')) {
            $apiKey = $this->method->getConfigData('test_api_key');
        }
        
        return [
            'appId' => \Lyra\LyraIndia\Model\Payment\LyraIndia::LYRA_APP_ID,
            'merchantId' => $merchantId,
            'shopId' => $shopId,
            'apiKey' => $apiKey,
            'testMode' => $this->method->getConfigData('test_mode'),
            'platform' => 'MAGENTO2',
            'version' => \Lyra\LyraIndia\Model\Payment\LyraIndia::LYRA_VERSION
        ];
    }
    
    protected function redirectToFinal($successFul = true, $message="") {

        if($successFul){
            if($message) $this->messageManager->addSuccessMessage(__($message));

            // if ($this->order->getCustomerIsGuest()) {
            //     $params = [
            //         '_direct' => 'sales/guest/view',
            //         '_query' => [
            //             'order_increment_id' => $this->order->getIncrementId(),
            //             'email' => $this->order->getCustomerEmail(),
            //             'lastname' => $this->order->getBillingAddress()->getLastname(),
            //         ]
            //     ];
            //     $url = $this->_url->getUrl('', $params);
            // } else {
            //     $url = $this->_url->getUrl('customer/account/viewOrder', ['order_id' => $this->order->getId()]);
            // }

            // Always redirect to the standard Magento success page
            return $this->_redirect('checkout/onepage/success');

            //return $this->_redirect($url);
        } else {
            if($message) $this->messageManager->addErrorMessage(__($message));
            return $this->_redirect('checkout/onepage/failure');
        }
    }

    public function createTransaction($order = null, $paymentData = array())
    {
        try {
            //get payment object from order object
            $payment = $order->getPayment();
            $payment->setLastTransId($paymentData['uuid']);
            $payment->setTransactionId($paymentData['uuid']);
            $payment->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
            );

            $payment->setIsTransactionClosed(false); //Kept this for refund process
            $payment->setShouldCloseParentTransaction(false); //Kept this for refund process

            $formatedPrice = $order->getBaseCurrency()->formatTxt(
                $order->getGrandTotal()
            );

            $invoice = $order->prepareInvoice();
            $invoice->setTransactionId($paymentData['uuid']);
            $invoice->register();
            $invoice->pay();

            $order->addRelatedObject($invoice);
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);

            $message = __('The authorized amount is %1.', $formatedPrice);

            //get the object of builder class
            $trans = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($paymentData['uuid'])
            ->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
            )
            ->setFailSafe(true)
            //build method creates the transaction and returns the object
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId(null);
            
            $payment->save();
            $order->save();

            return  $transaction->save()->getTransactionId();
        } catch (Exception $e) {
            //log errors here
        }
    }

}
