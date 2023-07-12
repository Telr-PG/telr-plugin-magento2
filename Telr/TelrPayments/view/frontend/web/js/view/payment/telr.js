define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'telr_telrpayments',
                component: 'Telr_TelrPayments/js/view/payment/method-renderer/telr-telrpayments'
            }
        );
        return Component.extend({});
    }
 );
