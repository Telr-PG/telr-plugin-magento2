<?php

namespace Telr\TelrPayments\Controller\Standard;

class Iframe extends \Telr\TelrPayments\Controller\TelrPayments {

    public function execute() {
        $order = $this->getOrder();

        if ($order->getBillingAddress()) {
            $payment_url = $this->getTelrModel()->buildTelrRequest($order);
            echo $payment_url; exit;              
        } else {
        }
    }
}
