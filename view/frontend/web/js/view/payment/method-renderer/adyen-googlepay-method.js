/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen NV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
define(
    [
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/view/payment/method-renderer/adyen-pm-method',
    ],
    function(
        quote,
        adyenPaymentMethod,
    ) {
        return adyenPaymentMethod.extend({
            placeOrderButtonVisible: false,
            txVariant: 'googlepay',
            initialize: function () {
                this._super();
            },
            buildComponentConfiguration: function (paymentMethod, paymentMethodsExtraInfo) {
                let baseComponentConfiguration = this._super();

                let googlePayConfiguration = Object.assign(baseComponentConfiguration, paymentMethodsExtraInfo[paymentMethod.type].configuration);
                googlePayConfiguration.showPayButton = true;

                return googlePayConfiguration
            }
        })
    }
);
