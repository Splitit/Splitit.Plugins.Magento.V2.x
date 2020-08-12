/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Splitit_Paymentmethod/js/action/set-payment-method-action',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data'
    ],
    function (
        $,
        Component,
        setPaymentMethodAction,
        additionalValidators,
        quote,
        customerData
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Splitit_Paymentmethod/payment/splitit-redirect-form',
                billingAgreement: ''
            },

            /** Init observable variables */
            initObservable: function () {
                
                this._super()
                    .observe('billingAgreement');

                return this;
            },

            /** Open window with  */
            showAcceptanceWindow: function (data, event) {
                var left = (screen.width - 433)/2;
                var top = (screen.height/2)-(window.innerHeight/2);
                var win= window.open(event.currentTarget.href,"Tell me more","width=433,height=607,left="+left+",top="+top+",location=no,status=no,scrollbars=no,resizable=no");
                win.document.writeln("<body style='margin:0px'><img width=100% src='"+event.currentTarget.href+"' />");
                win.document.writeln("</body>");
                win.document.write('<title>Splitit Learn More</title>');
                

                return false;
            },

            /** Returns Splitit tell me more link path */
            getPaymentAcceptanceMarkHref: function () {
                return window.checkoutConfig.payment.splititExpress.paymentAcceptanceMarkHref;
            },

            /** Returns Splitit logo image path */
            getPaymentAcceptanceMarkSrc: function () {
                return window.checkoutConfig.payment.splititExpress.paymentAcceptanceMarkSrc;
            },

            /** Returns Splitit tell me more image path */
            questionMark: function () {
                return window.checkoutConfig.payment.splititExpress.questionMark;
            },

            /** Returns billing agreement data */
            getBillingAgreementCode: function () {
                return window.checkoutConfig.payment.splititExpress.billingAgreementCode[this.item.method];
            },

            /** Returns payment information data */
            getData: function () {
                var parent = this._super(),
                    additionalData = null;

                if (this.getBillingAgreementCode()) {
                    additionalData = {};
                    additionalData[this.getBillingAgreementCode()] = this.billingAgreement();
                }

                return $.extend(true, parent, {
                    'additional_data': additionalData
                });
            },

            /** Redirect to splitit */
            continueToSplitit: function () {
                
                if (additionalValidators.validate()) {
                    //update payment method information if additional data was changed
                    this.selectPaymentMethod();
                    setPaymentMethodAction(this.messageContainer).done(
                        function () {
                            customerData.invalidate(['cart']);
                            $.mage.redirect(
                                window.checkoutConfig.payment.splititExpress.redirectUrl[quote.paymentMethod().method]
                            );
                        }
                    );

                    return false;
                }
            }
        });
    }
);