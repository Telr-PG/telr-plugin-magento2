<?php

namespace Telr\TelrPayments\Model;

use Telr\TelrPayments\Helper\TelrPayments as TelrPaymentsHelper;
use Magento\Quote\Model\Quote\Payment;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Framework\Message\ManagerInterface;
use Magento\Vault\Model\CreditCardTokenFactory;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;

class TelrPayments extends \Magento\Payment\Model\Method\AbstractMethod {
    const CODE = 'telr_telrpayments';
    protected $_code = self::CODE;
    protected $_isGateway = false;
    protected $_isOffline = true;
    protected $helper;
    protected $logger;
    protected $_minAmount = null;
    protected $_maxAmount = null;
    protected $_orderFactory;
    protected $_checkoutSession;
    protected $orderManagement;
    protected $orderSender;
    protected $_order;
    protected $_invoiceService;
    protected $_transaction;
    protected $_creditmemoFactory;
    protected $_creditmemoService;
    protected $transactionFactory;
    protected $_messageManager;
    
    protected $creditCardTokenFactory;
    protected $paymentTokenRepository;
    protected $paymentTokenManagement;
    protected $_priceCurrencyInterface;

    protected $_supportedCurrencyCodes = array(
        'AFN', 'ALL', 'DZD', 'ARS', 'AUD', 'AZN', 'BSD', 'BDT', 'BBD',
        'BZD', 'BMD', 'BOB', 'BWP', 'BRL', 'GBP', 'BND', 'BGN', 'CAD',
        'CLP', 'CNY', 'COP', 'CRC', 'HRK', 'CZK', 'DKK', 'DOP', 'XCD',
        'EGP', 'EUR', 'FJD', 'GTQ', 'HKD', 'HNL', 'HUF', 'INR', 'IDR',
        'ILS', 'JMD', 'JPY', 'KZT', 'KES', 'LAK', 'MMK', 'LBP', 'LRD',
        'MOP', 'MYR', 'MVR', 'MRO', 'MUR', 'MXN', 'MAD', 'NPR', 'TWD',
        'NZD', 'NIO', 'NOK', 'PKR', 'PGK', 'PEN', 'PHP', 'PLN', 'QAR',
        'RON', 'RUB', 'WST', 'SAR', 'SCR', 'SGF', 'SBD', 'ZAR', 'KRW',
        'LKR', 'SEK', 'CHF', 'SYP', 'THB', 'TOP', 'TTD', 'TRY', 'UAH',
        'AED', 'USD', 'VUV', 'VND', 'XOF', 'YER'
    );

    protected $_formBlockType = 'Telr\TelrPayments\Block\Form\TelrPayments';
    protected $_infoBlockType = 'Telr\TelrPayments\Block\Info\TelrPayments';

    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canRefundInvoicePartial = true;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        OrderSender $orderSender,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Telr\TelrPayments\Helper\TelrPayments $helper,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService,    
        \Magento\Sales\Model\Order\Invoice $Invoice,
        \Magento\Framework\DB\Transaction $transaction,
        CreditCardTokenFactory $creditCardTokenFactory,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        PaymentTokenManagementInterface $paymentTokenManagement,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrencyInterface,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->orderSender = $orderSender;
        $this->orderManagement = $orderManagement;
        $this->_invoiceService = $invoiceService;
        $this->_invoiceSender = $invoiceSender;
        $this->_transaction = $transaction;
        $this->_creditmemoFactory = $creditmemoFactory;
        $this->_creditmemoService = $creditmemoService;
        $this->_invoice = $Invoice;
        $this->transactionFactory = $transactionFactory;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_messageManager = $messageManager;
        $this->creditCardTokenFactory = $creditCardTokenFactory;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->_priceCurrencyInterface = $priceCurrencyInterface;

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger
        );

        $this->_minAmount = $this->getConfigData('min_order_total');
        $this->_maxAmount = $this->getConfigData('max_order_total');
    }

    public function getCheckoutSession() {
        return $this->_checkoutSession;
    }

    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canRefund()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available.'));
        }

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for refund.'));
        }

        if (!$payment->getTransactionId()) {
            return false;
        }

        $storeId = $this->getConfigData("store_id");
        $remoteApiKey = $this->getConfigData('remote_api_auth_key');

        if ($remoteApiKey == "") {
            throw new \Magento\Framework\Exception\LocalizedException(__('Please configure Telr Remote API Key to initiate the refund.'));
        }

        $baseToOrderRate = $payment->getOrder()->getBaseToOrderRate();
        $amount = $amount * $baseToOrderRate;

        $payment->setAmount($amount);

        $txnId = $payment->getTransactionId();
        $txnId = explode("-", $txnId)[0];

        $data =array(
            'ivp_store' => $storeId,
            'ivp_authkey' => $remoteApiKey,
            'ivp_trantype' => 'refund',
            'ivp_tranclass' => 'ecom',
            'ivp_currency' => $payment->getOrder()->getOrderCurrencyCode(),
            'ivp_amount' => $amount,
            'ivp_test' => $this->getConfigData('sandbox') ? 1 : 0,
            'tran_ref'     => $txnId 
        );

        $response = $this->remoteApiRequest($data);
        parse_str($response, $parsedResponse);

        if($parsedResponse['auth_status'] != 'A'){
            throw new \Magento\Framework\Exception\LocalizedException(__('The refund with Telr is failed with message: ' . $parsedResponse['auth_message']));
        }else{
        	$payment
	            ->setIsTransactionClosed(1)
	            ->setShouldCloseParentTransaction(1);
        }
        
        return $this;
    }

    public function capture($payment, $amount)
    {
        if (!$this->canCapture()) {
            Mage::throwException($this->_getHelper()->__('Capture action is not available'));
        }

        $storeId = $this->getConfigData("store_id");
        $remoteApiKey = $this->getConfigData('remote_api_auth_key');

        if ($remoteApiKey == "") {
            throw new \Magento\Framework\Exception\LocalizedException(__('Please configure Telr Remote API Key to capture payments online.'));
        }

        if(!isset($payment->getAdditionalInformation()['telr_ref'])){
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid transaction ID.'));
        }

        $data =array(
            'ivp_store' => $storeId,
            'ivp_authkey' => $remoteApiKey,
            'ivp_trantype' => 'capture',
            'ivp_tranclass' => 'ecom',
            'ivp_currency' => $payment->getOrder()->getOrderCurrencyCode(),
            'ivp_amount' => $amount,
            'ivp_test' => $this->getConfigData('sandbox') ? 1 : 0,
            'tran_ref'     => $payment->getAdditionalInformation()['telr_ref'] 
        );

        $response = $this->remoteApiRequest($data);
        parse_str($response, $parsedResponse);

        if($parsedResponse['auth_status'] != 'A'){
            throw new \Magento\Framework\Exception\LocalizedException(__('The Capture Request with Telr is failed with message: ' . $parsedResponse['auth_message']));
        }
 
        /*echo "<pre>"; print_r($amount); 
        echo "<pre>"; print_r($payment->getOrder()->getOrderCurrencyCode()); exit;*/

        return $this;
    }

    public function void($payment)
    {
        if (!$this->canCapture()) {
            Mage::throwException($this->_getHelper()->__('Capture action is not available'));
        }

        $storeId = $this->getConfigData("store_id");
        $remoteApiKey = $this->getConfigData('remote_api_auth_key');

        $this->_order = $payment->getOrder();

        if ($remoteApiKey == "") {
            throw new \Magento\Framework\Exception\LocalizedException(__('Please configure Telr Remote API Key to capture payments online.'));
        }

        if(!isset($payment->getAdditionalInformation()['telr_ref'])){
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid transaction ID.'));
        }

        $data =array(
            'ivp_store' => $storeId,
            'ivp_authkey' => $remoteApiKey,
            'ivp_trantype' => 'release',
            'ivp_tranclass' => 'ecom',
            'ivp_currency' => $payment->getOrder()->getOrderCurrencyCode(),
            'ivp_amount' => $payment->getOrder()->getGrandTotal(),
            'ivp_test' => $this->getConfigData('sandbox') ? 1 : 0,
            'tran_ref'     => $payment->getAdditionalInformation()['telr_ref'] 
        );

        $response = $this->remoteApiRequest($data);
        parse_str($response, $parsedResponse);

        if($parsedResponse['auth_status'] != 'A'){
            throw new \Magento\Framework\Exception\LocalizedException(__('The Release Amount is failed with message: ' . $parsedResponse['auth_message']));
        }

        $this->paymentVoided($payment->getAdditionalInformation()['telr_ref'] , $payment->getOrder()->getOrderCurrencyCode(), $payment->getOrder()->getGrandTotal());

        return $this;
    }

    public function remoteApiRequest($data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://secure.telr.com/gateway/remote.html');
        curl_setopt($ch, CURLOPT_POST, count($data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        $results = curl_exec($ch);
        curl_close($ch);
        return $results;
    }

    /**
     * Determine method availability based on [CURL, quote amount,config data]
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {

        if (function_exists('curl_init') == false) {
            return false;
        }

        if ($quote && (
                $quote->getBaseGrandTotal() < $this->_minAmount
                || ($this->_maxAmount && $quote->getBaseGrandTotal() > $this->_maxAmount))
        ) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    public function canUseForCurrency($currencyCode) {
        return true;
    }

    private function requestGateway($api_url, $params) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, count($params));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
        $returnData = json_decode(curl_exec($ch),true);
        curl_close($ch);
        return $returnData;
    }


    /**
     * Payment request
     *
     * @param $order Object
     * @throws \Magento\Framework\Validator\Exception
     */
    public function buildTelrRequest($order) {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
        $version = $productMetadata->getVersion(); //will return the magento version

        $this->_order=$order;
        $billing_address = $this->_order->getBillingAddress();
        $shipping_address = $this->_order->getShippingAddress();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        $payment = $this->_order->getPayment();
        $paymentToken = $payment->getAdditionalInformation();

        $ivp_framed = ($this->getConfigData('ivp_framed') == 1 ) ? 2 : 0; 
        $telr_lang = $this->getConfigData('telr_lang');

        $txnType = $this->getConfigData("telr_txn_type");

        if($txnType != ''){
            $params['ivp_trantype'] = $txnType;
        }

        $params['ivp_method']          = 'create';
        $params['ivp_store']           = $this->getConfigData("store_id");
        $params['ivp_authkey']         = $this->getConfigData("auth_key");
        $params['ivp_desc']            = $this->getConfigData("transaction_desc");
        $params['ivp_test']            = $this->getConfigData('sandbox') ? 1 : 0;
        $params['ivp_source']          = $version;
        $params['ivp_cart']            = $this->_order->getRealOrderId().'_'.(string)time();
        $params['ivp_currency']        = $this->_order->getOrderCurrencyCode();
        $params['ivp_amount']          = round($this->_order->getGrandTotal(), 2);
        $params['bill_fname']          = $billing_address->getFirstname();
        $params['bill_sname']          = $billing_address->getLastname();
        $params['bill_addr1']          = $billing_address->getStreet()[0];
        $params['ivp_framed']          = $ivp_framed;
        $params['ivp_lang']            = $telr_lang;

        if(isset($paymentToken['payment_token']) && $paymentToken['payment_token'] != ''){
            $params['ivp_paymethod']            = "card";
            $params['card_token']            = $paymentToken['payment_token'];
        }

        if (count($billing_address->getStreet()) > 1) {
            $params['bill_addr2']  = $billing_address->getStreet()[1];
        }

        if (count($billing_address->getStreet()) > 2) {
            $params['bill_addr3']  = $billing_address->getStreet()[2];
        }

        $params['bill_city']           = $billing_address->getCity();
        $params['bill_region']         = $billing_address->getRegion();
        $params['delv_zip']            = $billing_address->getPostcode();
        $params['bill_country']        = $billing_address->getCountryId();
        $params['bill_email']          = $this->_order->getCustomerEmail();
        $params['bill_phone1']         = $billing_address->getTelephone();
        $params['return_auth']         = $this->getReturnUrl().'?coid='.$this->_order->getRealOrderId();
        $params['return_can']          = $this->getCancelUrl().'?coid='.$this->_order->getRealOrderId();
        $params['return_decl']         = $this->getCancelUrl().'?coid='.$this->_order->getRealOrderId();
        $params['ivp_update_url']      = $this->getIvpCallbackUrl() . "?cart_id=" . $this->_order->getRealOrderId();

        if($this->isSSL() && $customerSession->isLoggedIn()) {
            $params['bill_custref'] = $customerSession->getCustomerId();
        }

        $api_url = $this->getConfigData('sandbox') ? $this->getConfigData('api_url_sandbox') : $this->getConfigData('api_url');

        try {
            $results = $this->requestGateway($api_url, $params);
            $url = false;
            if (isset($results['order']['ref']) && isset($results['order']['url'])) {
                $ref = trim($results['order']['ref']);
                $url = trim($results['order']['url']);
                $this->getCheckoutSession()->setOrderRef($ref);

                $paymentToken['telr_order_ref'] = $ref;

                $payment->setAdditionalInformation($paymentToken);
                $payment->save();

                return $url;
            }else{
                if(isset($results['error'])){
                    $_SESSION['telr_error_message'] = $results['error']['message'] . ": " . $results['error']['note'];
                    $this->_messageManager->addError(__($results['error']['message'] . ": " . $results['error']['note']));
                }
                return false;
            }
        } catch (Exception $e) {
            $this->debugData(['request' => $requestData, 'exception' => $e->getMessage()]);
        }
        return false;
    }

    private function notifyOrder() {
        $this->orderSender->send($this->_order);
        $this->order->addStatusHistoryComment('Customer email sent')->setIsCustomerNotified(true)->save();
    }

    public function getConfig($key){
        return $this->getConfigData($key);
    }

    /**
     * Return the provided comment as either a string or a order status history object
     *
     * @param string $comment
     * @param bool $makeHistory
     * @return string|\Magento\Sales\Model\Order\Status\History
     */
    protected function addOrderComment($comment,$makeHistory=false) {
        $message=$comment;
        if ($makeHistory) {
            $message=$this->_order->addStatusHistoryComment($message);
            $message->setIscustomerNotified(null);
        }
        return $message;
    }

    private function registerAuth($message,$txref) {
        $this->logDebug("registerAuth");

        $payment = $this->_order->getPayment();
        $payment->setTransactionId($txref);
        $payment->setIsTransactionClosed(false);
        $payment->setAdditionalInformation('telr_message', $message);
        $payment->setAdditionalInformation('telr_ref', $txref);
        $payment->setAdditionalInformation('telr_status', \Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);
        $payment->place();

        try {
            $payment->setLastTransId($txref);
            $payment->setTransactionId($txref);

            $formatedPrice = $this->_order->getBaseCurrency()->formatTxt(
                $this->_order->getGrandTotal()
            );
 
            $message = __('The authorized amount in Telr is %1.', $formatedPrice);
            //get the object of builder class
            $trans = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
            ->setOrder($this->_order)
            ->setTransactionId($txref)
            ->setFailSafe(true)
            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);
 
            $payment->addTransactionCommentsToOrder(
                $transaction,
                $message
            );
            $payment->setParentTransactionId($txref);
            $payment->save();
            $this->_order->save();
            $transaction->save();
        } catch (Exception $e) {
            
        }

        /*
        I've commented this line because it actually stores huge useless data that is ver hard to be investigated,.
        If you try var_dump() this in the browser it will hangup and the machine itself !
        */
        //$this->logDebug(print_r($payment->getData(),true));

    }

    private function registerPending($message,$txref) {
        $this->logDebug("registerPending");

        $payment = $this->_order->getPayment();
        $payment->setTransactionId($txref);
        $payment->setIsTransactionClosed(0);
        $payment->setAdditionalInformation('telr_message', $message);
        $payment->setAdditionalInformation('telr_ref', $txref);
        $payment->setAdditionalInformation('telr_status', 'Pending');
        $payment->place();

        /*
        I've commented this line because it actually stores huge useless data that is ver hard to be investigated,.
        If you try var_dump() this in the browser it will hangup and the machine itself !
        */
        //$this->logDebug(print_r($payment->getData(),true));

    }

    private function registerCapture($message,$txref) {
        $this->logDebug("registerCapture");

        $payment = $this->_order->getPayment();
        $payment->setTransactionId($txref);
        $payment->setIsTransactionClosed(0);
        $payment->setAdditionalInformation('telr_message', $message);
        $payment->setAdditionalInformation('telr_ref', $txref);
        $payment->setAdditionalInformation('telr_status', \Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
        $payment->place();

        /*
        I've commented this line because it actually stores huge useless data that is ver hard to be investigated,.
        If you try var_dump() this in the browser it will hangup and the machine itself !
        */
        //$this->logDebug(print_r($payment->getData(),true));

    }

    private function updateOrder($message, $state, $status, $notify) {
        $this->logDebug("updateOrder");
        if ($state) {
            $this->_order->setState($state);
            if ($status) {
                $this->_order->setStatus($status);
            }
            $this->_order->save();
        } else if ($status) {
            $this->_order->setStatus($status);
            $this->_order->save();
        }
        if ($message) {
            $this->_order->addStatusHistoryComment($message);
            $this->_order->save();
        }
        $this->logDebug("OrderState = ".$this->_order->getState());
        $this->logDebug("OrderStatus = ".$this->_order->getStatus());
        if ($notify) {
            $this->notifyOrder();
        }
    }

    private function getStateCode($name) {
        if (strcasecmp($name,"processing")==0) { return \Magento\Sales\Model\Order::STATE_PROCESSING; }
        if (strcasecmp($name,"review")==0)     { return \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW; }
        if (strcasecmp($name,"paypending")==0) { return \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT; }
        if (strcasecmp($name,"pending")==0)    { return \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT; }
        if (strcasecmp($name,"cancelled")==0)   { return \Magento\Sales\Model\Order::STATE_CANCELED; }
        if (strcasecmp($name,"canceled")==0)   { return \Magento\Sales\Model\Order::STATE_CANCELED; }
        if (strcasecmp($name,"closed")==0)   { return \Magento\Sales\Model\Order::STATE_CLOSED; }
        if (strcasecmp($name,"holded")==0)   { return \Magento\Sales\Model\Order::STATE_HOLDED; }
        if (strcasecmp($name,"complete")==0)   { return \Magento\Sales\Model\Order::STATE_COMPLETE; }
        if (strcasecmp($name,"fraud")==0)   { return \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW; }
        return false;
    }

    /**
     * Transaction was authorised
     */
    private function paymentCompleted($txref) {
        $this->registerCapture('Payment completed',$txref);
        $message='Payment completed by Telr: '.$txref;
        $state=$this->getStateCode("processing");
        $this->updateOrder($message, $state, "pending", false);
    }

    /**
     * Transaction has not been completed (deferred payment method, or on hold)
     */
    private function paymentPending($txref) {
        $this->registerPending('Payment pending',$txref);
        $message='Payment pending by Telr: '.$txref;
        $state=$this->getStateCode("paypending");
        $this->updateOrder($message, $state, $state, false);
    }

    /**
     * Transaction has not been authorised but completed (auth method used, or sale put on hold)
     */
    private function paymentAuthorised($txref) {
        $this->registerAuth('Payment authorised',$txref);
        $message='Payment authorisation by Telr: '.$txref;
        $state=$this->getStateCode("processing");
        $this->updateOrder($message, $state, $state, false);
    }

    /**
     * Transaction has been refunded (may be partial refund)
     */
    private function paymentRefund($txref, $currency, $amount) {
        $message='Refund of '.$currency.' '.$amount.': '.$txref;
        $this->updateOrder($message, false, false, false);
    }

    /**
     * Transaction has been voided
     */
    private function paymentVoided($txref, $currency, $amount) {
        $message='Void of '.$currency.' '.$amount.': '.$txref;
        $this->updateOrder($message, $this->getStateCode("canceled"), $this->getStateCode("canceled"), false);
    }

    /**
     * Transaction request has been cancelled
     */
    private function paymentCancelled() {
        $message='Payment request cancelled by Telr';
        $state=$this->getStateCode("cancelled");
        $this->updateOrder($message, $state, $state, false);
    }

    public function logDebug($message) {
        $dbg['telr']=$message;
        $this->logger->debug($dbg,null,true);
    }

    /**
     * Payment request validation
     */
    public function validateResponse($order_id) {

        $validateResponse = array(
            'status' => false,
            'message' => 'Unable to get payment information.'
        );

        $api_url = $this->getConfigData('sandbox') ? $this->getConfigData('api_url_sandbox') : $this->getConfigData('api_url');
        $auth_key = $this->getConfigData('auth_key');
        $store_id = $this->getConfigData('store_id');
        $defaultStatus = $this->getConfigData('order_status');
        //$telr_order_ref = $this->getCheckoutSession()->getOrderRef();
        //$this->_order=$this->_orderFactory->create()->load($order_id);
        $this->_order = $this->_orderFactory->create()->loadByIncrementId($order_id);


        $payment = $this->_order->getPayment();
        $addonInfo = $payment->getAdditionalInformation();

        if(isset($addonInfo['telr_order_ref'])){
            $telr_order_ref = $addonInfo['telr_order_ref'];
        }else{
            $validateResponse['status_code'] = -1;
            $this->logDebug("Error: telr_order_ref is NULL in magento database table against order id ".$order_id);
            $this->logDebug("Order status updated as CANCELLED against order id ".$order_id);
            return $validateResponse;
        }

        $params = array(
            'ivp_method'   => 'check',
            'ivp_store'    => $store_id,
            'ivp_authkey'  => $auth_key,
            'order_ref'    => $telr_order_ref
        );

        $results = $this->requestGateway($api_url, $params);

        $objOrder='';
        $objError='';
        if (isset($results['order'])) { $objOrder = $results['order']; }
        if (isset($results['error'])) { $objError = $results['error']; }
        if (is_array($objError)) { // Failed
             return $validateResponse;
        }
        if (!isset(
            $objOrder['cartid'],
            $objOrder['status']['code'],
            $objOrder['transaction']['status'],
            $objOrder['transaction']['ref']))
        {
            if(isset($objOrder['status']['code']))
            {
                $validateResponse['status_code'] = $objOrder['status']['code'];
                $this->logDebug("Order status updated as CANCELLED against order id ".$order_id);
            }
            return $validateResponse;
        }

        $new_tx=$objOrder['transaction']['ref'];
        $ordStatus=$objOrder['status']['code'];
        $txStatus=$objOrder['transaction']['status'];
        $txMessage=$objOrder['transaction']['message'];
        $validateResponse['message'] = $txMessage;
        $cart_id=$objOrder['cartid'];
        $parts=explode('~', $cart_id, 2);
        $order_id=$parts[0];
        if (($ordStatus==-1) || ($ordStatus==-2) || ($ordStatus==-3) || ($ordStatus==-4)) {
            // Order status EXPIRED (-1) or CANCELLED (-2)
            $this->paymentCancelled($new_tx);
            $validateResponse['message'] = $txMessage;
            return $validateResponse;
        }
        if ($ordStatus==4) {
            // Order status PAYMENT_REQUESTED (4)
            $this->paymentPending($new_tx);
            $validateResponse['status'] = true;
            return $validateResponse;
        }
        if ($ordStatus==1) {
            $validateResponse['message'] = 'Payment Pending';
            return $validateResponse;
        }
        if ($ordStatus==2) {
            // Order status AUTH (2)
            $this->paymentAuthorised($new_tx);
            $validateResponse['status'] = true;
            return $validateResponse;
        }
        if ($ordStatus==3) {
            // Order status PAID (3)
            if ($txStatus=='P') {
                // Transaction status of pending or held
                $this->paymentPending($new_tx);
                $validateResponse['status'] = true;
                return $validateResponse;
            }
            if ($txStatus=='H') {
                // Transaction status of pending or held
                $this->paymentAuthorised($new_tx);
                $validateResponse['status'] = true;
                return $validateResponse;
            }
            if ($txStatus=='A') {
                // Transaction status = authorised
                if($defaultStatus != ''){
                     $this->updateOrderStatusWithMessage($this->_order, $defaultStatus, $new_tx);
                }else{
                    $this->paymentCompleted($new_tx);
                }
                if($this->_order->canInvoice()) {
                    $invoice = $this->_invoiceService->prepareInvoice($this->_order);
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                    $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
                    $invoice->setLastTransId($new_tx);
                    $invoice->setTransactionId($new_tx);
                    $invoice->register();
                    $invoice->save();
                    $transactionSave = $this->_transaction->addObject(
                        $invoice
                    )->addObject(
                        $invoice->getOrder()
                    );
                    $transactionSave->save();
                    $this->_invoiceSender->send($invoice);
                    //send notification code
                    $this->_order->addStatusHistoryComment(
                        __('Notified customer about invoice #%1.', $invoice->getId())
                    )
                    ->setIsCustomerNotified(true)
                    ->save();

                    try {
                        //get payment object from order object
                        $payment = $this->_order->getPayment();
                        $payment->setLastTransId($new_tx);
                        $payment->setTransactionId($new_tx);
                        /*$payment->setAdditionalInformation(
                            [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $objOrder]
                        );*/
                        $formatedPrice = $this->_order->getBaseCurrency()->formatTxt(
                            $this->_order->getGrandTotal()
                        );
             
                        $message = __('The authorized amount is %1.', $formatedPrice);
                        //get the object of builder class
                        $trans = $this->_transactionBuilder;
                        $transaction = $trans->setPayment($payment)
                        ->setOrder($this->_order)
                        ->setTransactionId($new_tx)
                        /*->setAdditionalInformation(
                            [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $objOrder]
                        )*/
                        ->setFailSafe(true)
                        //build method creates the transaction and returns the object
                        ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
             
                        $payment->addTransactionCommentsToOrder(
                            $transaction,
                            $message
                        );
                        $payment->setParentTransactionId($new_tx);
                        $payment->save();
                        $this->_order->save();
                        $transaction->save();
                    } catch (Exception $e) {
                        
                    }
                }

                //Check & Save Card
                $Spayment = $this->_order->getPayment();
                $paymentToken = $Spayment->getAdditionalInformation();
                if(isset($paymentToken['save_card']) && $paymentToken['save_card'] == 'yes' && isset($results['order']['card']) && !$this->_order->getCustomerIsGuest()){
                    try{
                        $cardDetails = $results['order']['card'];
                        $newToken = $this->creditCardTokenFactory->create();
                        $newToken->setExpiresAt($cardDetails['expiry']['year'] . '-' . $cardDetails['expiry']['month']);
                        $newToken->setGatewayToken($new_tx);
                        $newToken->setTokenDetails(json_encode([
                                                    'type'          => $cardDetails['type'],
                                                    'first6'        => $cardDetails['first6'],
                                                    'last4'         => $cardDetails['last4'],
                                                    'expiry_month'  => $cardDetails['expiry']['month'],
                                                    'expiry_year'   => $cardDetails['expiry']['year']
                                                ]));
                        $newToken->setIsActive(true);
                        $newToken->setIsVisible(true);
                        $newToken->setPaymentMethodCode('telr_telrpayments');
                        $newToken->setCustomerId($this->_order->getCustomerId());

                        $publicHash = $this->generatePublicHash($cardDetails['last4'], $cardDetails['expiry']['year'], $cardDetails['expiry']['month'], $this->_order->getCustomerId());
                        $newToken->setPublicHash($publicHash);

                        $existingTokens = $this->paymentTokenManagement->getByPublicHash( $publicHash, $this->_order->getCustomerId());
                        if(!$existingTokens){
                            $newToken->save();
                        }
                    }catch(Exception $e){

                    }
                }

                $validateResponse['status'] = true;
                return $validateResponse;
            }
        }
        // Declined
        return $validateResponse;
    }

    public function generatePublicHash($last4, $expYear, $expMonth, $customerId){
        return md5($last4 . '-' . $expYear . '-' . $expMonth . "-" . $customerId);
    }

    public function getRedirectUrl() {
        $url = $this->helper->getUrl($this->getConfigData('redirect_url'));
        return $url;
    }

    public function getIframeUrl() {
        $url = $this->helper->getUrl('telr/standard/iframe')  . "?tx=" . time();
        return $url;
    }

    public function getFramedMode() {
        $ivp_framed = ($this->getConfigData('ivp_framed') == 1 && $this->isSSL()) ? 'yes' : 'no';
        return $ivp_framed;
    }

    public function getReturnUrl() {
        $url = $this->helper->getUrl($this->getConfigData('return_url'));
        return $url;
    }

    public function getCancelUrl() {
        $url = $this->helper->getUrl($this->getConfigData('cancel_url'));
        return $url;
    }

    public function getIvpCallbackUrl() {
        $url = $this->helper->getUrl($this->getConfigData('ivp_update_url'));
        return $url;
    }

    public function isSSL() {
        return true;
        $isSecure = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            $isSecure = true;
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
            $isSecure = true;
        }
        return $isSecure;
    }

    public function updateOrderStatusWithMessage($order, $status, $txnref, $addTxn = false){
        $this->_order = $order;
        $message = '';
        $state = \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT;
        switch ($status) {
            case 'complete':
                $message='Payment completed by Telr: ' . $txnref;
                $state=$this->getStateCode("processing");
                break;

            case 'canceled':
                $message='Payment request canceled by Telr: ' . $txnref;
                $state=$this->getStateCode("canceled");
                break;

            case 'refunded':
                $message='Transaction Refunded by Telr: ' . $txnref;
                $state=$this->getStateCode("closed");
                break;
            

            case 'processing':
                $message='Telr Transaction Reference: ' . $txnref;
                $state=$this->getStateCode("processing");
                break;
            

            case 'fraud':
                $message='Telr Transaction Reference: ' . $txnref;
                $state=$this->getStateCode("fraud");
                break;
            

            case 'complete':
                $message='Telr Transaction Reference: ' . $txnref;
                $state=$this->getStateCode("complete");
                break;

            case 'holded':
                $message='Telr Transaction Reference: ' . $txnref;
                $state=$this->getStateCode("holded");
                break;
            
            default:
                $message = 'Transaction ' . $status . ' by Telr: ' . $txnref;
                $state=$this->getStateCode($status);
                break;
        }
        
        $this->updateOrder($message, $state, $status, false);
        if($status == 'complete' && $addTxn){
        	if($this->_order->canInvoice()) {
                $invoice = $this->_invoiceService->prepareInvoice($this->_order);
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                $invoice->setState(\Magento\Sales\Model\Order\Invoice::STATE_PAID);
                $invoice->setLastTransId($txnref);
                $invoice->setTransactionId($txnref);
                $invoice->register();
                $invoice->save();
                $transactionSave = $this->_transaction->addObject(
                    $invoice
                )->addObject(
                    $invoice->getOrder()
                );
                $transactionSave->save();
                $this->_invoiceSender->send($invoice);
                //send notification code
                $this->_order->addStatusHistoryComment(
                    __('Notified customer about invoice #%1.', $invoice->getId())
                )
                ->setIsCustomerNotified(true)
                ->save();

                try {
                    //get payment object from order object
                    $payment = $this->_order->getPayment();
                    $payment->setLastTransId($txnref);
                    $payment->setTransactionId($txnref);
                    $formatedPrice = $this->_order->getBaseCurrency()->formatTxt(
                        $this->_order->getGrandTotal()
                    );
         
                    $message = __('The authorized amount is %1.', $formatedPrice);
                    //get the object of builder class
                    $trans = $this->_transactionBuilder;
                    $transaction = $trans->setPayment($payment)
                    ->setOrder($this->_order)
                    ->setTransactionId($txnref)
                    ->setFailSafe(true)
                    //build method creates the transaction and returns the object
                    ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
         
                    $payment->addTransactionCommentsToOrder(
                        $transaction,
                        $message
                    );
                    $payment->setParentTransactionId($txnref);
                    $payment->save();
                    $this->_order->save();
                    $transaction->save();
                } catch (Exception $e) {
                    
                }
            }
        }

        if($status == 'refunded'){
    		$invoices = $this->_order->getInvoiceCollection();
	        foreach($invoices as $invoice){
	            $invoiceincrementid = $invoice->getIncrementId();
    	        $invoiceobj =  $this->_invoice->loadByIncrementId($invoiceincrementid);
    	        $creditmemo = $this->_creditmemoFactory->createByOrder($this->_order);
    	        $this->_creditmemoService->refund($creditmemo); 
	        }
        }
    }
}
