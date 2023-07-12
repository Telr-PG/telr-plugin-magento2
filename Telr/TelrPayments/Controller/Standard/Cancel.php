<?php

namespace Telr\TelrPayments\Controller\Standard;

class Cancel extends \Telr\TelrPayments\Controller\TelrPayments {

    public function execute() {
    	$order_id = $this->getRequest()->getParam('coid');
    	$validateResponse = $this->getTelrModel()->validateResponse($order_id);
        if(!$validateResponse['status']) {
		   	$this->messageManager->addError($validateResponse['message']);
        }
        $this->_cancelPayment();
        $this->_checkoutSession->restoreQuote();
        $this->getResponse()->setRedirect(
            $this->getTelrHelper()->getUrl('checkout/cart')
        );
    }

}
