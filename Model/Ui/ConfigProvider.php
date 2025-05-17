<?php
namespace Lyra\LyraIndia\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Store\Model\Store as Store;

/**
 * Class ConfigProvider
 */
class ConfigProvider implements ConfigProviderInterface
{

    protected $method;
    protected $store;

    public function __construct(PaymentHelper $paymentHelper, Store $store)
    {
        $this->method = $paymentHelper->getMethodInstance(\Lyra\LyraIndia\Model\Payment\LyraIndia::CODE);
        $this->store = $store;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $publicKey = $this->method->getConfigData('live_api_key');
        if ($this->method->getConfigData('test_mode')) {
            $publicKey = $this->method->getConfigData('test_api_key');
        }
        
        $integrationType = 'standard';

        return [
            'payment' => [
                \Lyra\LyraIndia\Model\Payment\LyraIndia::CODE => [
                    'public_key' => $publicKey,
                    'integration_type' => $integrationType,
                    'api_url' => $this->store->getBaseUrl() . 'rest/',
                    'integration_type_standard_url' => $this->store->getBaseUrl() . 'lyraindia/payment/setup',
                    'recreate_quote_url' => $this->store->getBaseUrl() . 'lyraindia/payment/recreate',
                ]
            ]
        ];
    }
    
    public function getStore() {
        return $this->store;
    }
    
    /**
     * Get secret key for webhook process
     * 
     * @return array
     */
    public function getSecretKeyArray(){
        $data = ["live" => $this->method->getConfigData('live_api_key')];
        if ($this->method->getConfigData('test_mode')) {
            $data = ["test" => $this->method->getConfigData('test_api_key')];
        }
        
        return $data;
    }

    public function getPublicKey(){
        $publicKey = $this->method->getConfigData('live_public_key');
        if ($this->method->getConfigData('test_mode')) {
            $publicKey = $this->method->getConfigData('test_public_key');
        }
        return $publicKey;
    }
    
    
}
