<?php

namespace Telr\TelrPayments\Controller\Standard;

class Response extends \Telr\TelrPayments\Controller\TelrPayments {

    public function execute() {
        $order_id = $this->getRequest()->getParam('coid');
        $validateResponse = $this->getTelrModel()->validateResponse($order_id);
        if($validateResponse['status']) {
            $returnUrl = $this->getTelrHelper()->getUrl('checkout/onepage/success');
        } else {
            $this->messageManager->addError($validateResponse['message']);
            $this->_cancelPayment();
            $this->_checkoutSession->restoreQuote();
            $returnUrl = $this->getTelrHelper()->getUrl('checkout/onepage/failure');
        }
        $this->getResponse()->setRedirect($returnUrl);
    }

}
