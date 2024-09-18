<?php

namespace Telr\TelrPayments\Controller\Standard;

class Requery extends \Telr\TelrPayments\Controller\TelrPayments {

    public function execute() {
        $time = (new \DateTime())->modify('-30 minutes')->format('Y-m-d H:i:s');
        $returnUrl = $this->getTelrHelper()->getUrl('checkout/onepage/success');
        $collection = $this->_orderCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->setOrder('created_at','desc')
            ->addFieldToFilter('status',['eq' => 'pending'])
            ->addFieldToFilter('created_at', ['lt' => $time]);
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
            if(isset($resp['status_code']) && (($resp['status_code']==-1) || ($resp['status_code']==-2) || ($resp['status_code']==-3)))
            {   $order->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $order->save();
            }
        }
    }
}
