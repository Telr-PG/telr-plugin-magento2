<?php

namespace Telr\TelrPayments\Controller\Standard;

class Requery extends \Telr\TelrPayments\Controller\TelrPayments {

    public function execute() {
        $returnUrl = $this->getTelrHelper()->getUrl('checkout/onepage/success');
        echo "<pre>";
        $collection = $this->_orderCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->setOrder('created_at','desc')
            ->addFieldToFilter('status',
                ['eq' => 'pending']
            );
        $collection->getSelect()
            ->join(
                ["sop" => "sales_order_payment"],
                'main_table.entity_id = sop.parent_id',
                array('method')
            )
            ->where('sop.method = ?','telr_telrpayments');
        $collection->setOrder(
            'created_at',
            'desc'
        );

        foreach ($collection as $order) {
            $orderId = $order->getIncrementId();
            $resp = $this->getTelrModel()->validateResponse($orderId);
            echo "Processed Order Id: " . $orderId . "<br/>";
            echo "Response: " . print_r($resp) . "<br/>";
        }
        print_r("Processing Completed."); exit;
    }
}
