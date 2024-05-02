<?php
declare(strict_types=1);
namespace Telr\TelrPayments\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
/**
* Class AfterPlaceOrder
*/
class AfterPlaceOrder
{    
    /**
    * AfterPlaceOrder constructor
    * 
    */
    public function __construct() {}
    /**
    * Description afterPlace function
    *
    * @param OrderManagementInterface $subject
    * @param OrderInterface           $order
    *
    * @return OrderInterface
    * @throws LocalizedException
    */
    public function afterPlace(OrderManagementInterface $subject, OrderInterface $order): OrderInterface
    {        
        $methodId = $order->getPayment()->getMethodInstance()->getCode();		
        if (in_array($methodId,array('telr_telrpayments','telr_applepay'))) {
            $order->setCanSendNewEmailFlag(false);
        }		
        return $order;
    }
}
