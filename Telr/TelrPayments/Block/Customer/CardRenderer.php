<?php

namespace Telr\TelrPayments\Block\Customer;

use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\AbstractCardRenderer;

class CardRenderer extends AbstractCardRenderer
{
    /**
     * Can render specified token
     *
     * @param PaymentTokenInterface $token
     * @return boolean
     */
    public function canRender(PaymentTokenInterface $token)
    {
        return $token->getPaymentMethodCode() == 'telr_telrpayments';
    }

    /**
     * @return string
     */
    public function getNumberLast4Digits()
    {
        return $this->getTokenDetails()['last4'];
    }

    /**
     * @return string
     */
    public function getExpDate()
    {
        return $this->getTokenDetails()['expiry_month'] . "-" . $this->getTokenDetails()['expiry_year'];
    }

    public function getType()
    {
        return $this->getTokenDetails()['type'];
    }

    /**
     * @return string
     */
    public function getIconUrl()
    {
        return '';//$this->getIconForType($this->getTokenDetails()['type'])['url'];
    }

    /**
     * @return int
     */
    public function getIconHeight()
    {
        return '0';//$this->getIconForType($this->getTokenDetails()['type'])['height'];
    }

    /**
     * @return int
     */
    public function getIconWidth()
    {
        return '0';//$this->getIconForType($this->getTokenDetails()['type'])['width'];
    }
}
