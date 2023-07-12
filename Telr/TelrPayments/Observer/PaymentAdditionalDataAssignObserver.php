<?php

namespace Telr\TelrPayments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Payment\Observer\AbstractDataAssignObserver;

class PaymentAdditionalDataAssignObserver extends AbstractDataAssignObserver
{
    const MY_FIELD_NAME_INDEX = 'payment_token';
    const MY_FIELD_SAVE_CARD = 'save_card';

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_array($additionalData) || !isset($additionalData[self::MY_FIELD_NAME_INDEX])) {
            return; // or throw exception depending on your logic
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);
        $paymentInfo->setAdditionalInformation(
            self::MY_FIELD_NAME_INDEX,
            $additionalData[self::MY_FIELD_NAME_INDEX]
        );

        if (is_array($additionalData) && isset($additionalData[self::MY_FIELD_SAVE_CARD])) {
            $paymentInfo->setAdditionalInformation(
                self::MY_FIELD_SAVE_CARD,
                $additionalData[self::MY_FIELD_SAVE_CARD]
            );
        }
    }
}