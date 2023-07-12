<?php

namespace Telr\TelrPayments\Controller\Standard;

class Redirect extends \Telr\TelrPayments\Controller\TelrPayments {

    public function execute() {
        $order = $this->getOrder();

        if ($order->getBillingAddress()) {
            $payment_url = $this->getTelrModel()->buildTelrRequest($order);
            if ($payment_url) {
              $ivp_framed = ($this->getTelrModel()->getConfig('ivp_framed') == 1 && $this->getTelrModel()->isSSL()) ? true : false;

              $ivp_framed = false;
              // Check if Payment mode = framed & SSL is active, else proceed with regular checkout page.
              if($ivp_framed){
                  $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                  $customerSession = $objectManager->get('Magento\Customer\Model\Session');
                  $customerSession->setTelrPaymentUrl($payment_url);
                  $_SESSION['telr_payment_url'] = $payment_url;
                  $this->getResponse()->setRedirect($this->getTelrHelper()->getUrl('telr/standard/process') . "?tx=" . time());
              }else{
                  $this->getResponse()->setRedirect($payment_url);
              }
            } else {
              $this->_cancelPayment();
              $this->_checkoutSession->restoreQuote();
              if(isset($_SESSION['telr_error_message'])){
                $this->_messageManager->addError(__($_SESSION['telr_error_message']));
                unset($_SESSION['telr_error_message']);
              }
              $this->_messageManager->addError(__('Sorry, unable to process your transaction at this time.'));
              $this->getResponse()->setRedirect($this->getTelrHelper()->getUrl('checkout/cart'));
            }
        } else {
            $this->_cancelPayment();
            $this->_checkoutSession->restoreQuote();
            $this->getResponse()->setRedirect($this->getTelrHelper()->getUrl('checkout'));
        }
    }

}
