define(
    [
        'ko',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/resource-url-manager',
        'Magento_Checkout/js/model/payment-service',
        'mage/storage',
        'mage/url',
        'Magento_Checkout/js/action/get-totals',
        'mage/translate',
        'Magento_Checkout/js/model/payment/method-list'
    ],
    function(
        ko,
        $,
        quote,
        urlManager,
        paymentService,
        storage,
        urlBuilder,
        getTotalsAction
    ) {
        'use strict';

        return function (isLoading, payment) {
            var serviceUrl = urlBuilder.build('splititpaymentmethod/checkout/totals');
            var pageReloaded = (document.getElementById('pageReloaded')!=null)?document.getElementById('pageReloaded').value:1;

            return storage.post(
                serviceUrl,
                JSON.stringify({payment: payment,pageReloaded:pageReloaded})
            ).done(
                function(response) {
                    if(response) {
                        var deferred = $.Deferred();
                        isLoading(false);
                        getTotalsAction([], deferred);
                    }
                }
            ).fail(
                function (response) {
                    isLoading(false);
                }
            );
        }
    }
);