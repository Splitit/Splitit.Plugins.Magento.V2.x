
define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_CheckoutAgreements/js/model/agreements-assigner'
    ],
    function ($, quote, urlBuilder, storage, errorProcessor, customer, fullScreenLoader, agreementsAssigner) {
        'use strict';
        return function (messageContainer) {
            var serviceUrl,
                payload,
                method = 'put',
                paymentData = quote.paymentMethod(),
                agreementForm = $('div[data-role=checkout-agreements] input'),
                agreementData = agreementForm.serializeArray(),
                agreementIds = [];

                agreementData.forEach(function (item) {
                    agreementIds.push(item.value);
                });

                if (paymentData['extension_attributes'] === undefined) {
                    paymentData['extension_attributes'] = {};
                }

                paymentData['extension_attributes']['agreement_ids'] = agreementIds;
                console.log('paymentData===');
                console.log(paymentData);
                paymentData['additional_data']={installments_no:$('#select-num-of-installments').val()};
            
            /**
             * Checkout for guest and registered customer.
             */
            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/set-payment-information', {
                    cartId: quote.getQuoteId()
                });
                payload = {
                    cartId: quote.getQuoteId(),
                    email: quote.guestEmail,
                    paymentMethod: paymentData
                };
                method = 'post';
            } else {
                serviceUrl = urlBuilder.createUrl('/carts/mine/selected-payment-method', {});
                payload = {
                    cartId: quote.getQuoteId(),
                    method: paymentData
                };
            }
            fullScreenLoader.startLoader();

            return storage[method](
                serviceUrl, JSON.stringify(payload)
            ).fail(
                function (response) {
                    errorProcessor.process(response, messageContainer);
                    fullScreenLoader.stopLoader();
                }
            );
        };
    }
);
