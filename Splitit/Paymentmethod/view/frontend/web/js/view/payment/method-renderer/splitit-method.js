/**
 * Splitit_Payment Magento JS component
 *
 * @category    Splitit
 * @package     Splitit_Payment
 * @author      Ivan Weiler & Stjepan Udovičić
 * @copyright   Splitit (http://Splitit.net)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/*browser:true*/
/*global define*/
define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Splitit_Paymentmethod/payment/splitit-form'
            },

            getCode: function() {
                return 'splitit_paymentmethod';
            },

            isActive: function() {
                return true;
            },

            /** Open window with  */
            showAcceptanceWindow: function (data, event) {
                var left = (screen.width - 433)/2;
                var top = (screen.height/2)-(window.innerHeight/2);
                var win= window.open(event.currentTarget.href,"Tell me more","width=433,height=607,left="+left+",top="+top+",location=no,status=no,scrollbars=no,resizable=no");
                win.document.writeln("<body style='margin:0px'><img width=100% src='"+event.currentTarget.href+"' />");
                win.document.writeln("</body>");
                win.document.write('<title>Splitit Learn More</title>');
                /*window.open(
                    $(event.target).attr('href'),
                    'olcwhatissplitit',
                    'toolbar=no, location=no,' +
                    ' directories=no, status=no,' +
                    ' menubar=no, scrollbars=yes,' +
                    ' resizable=yes, ,left=0,' +
                    ' top=0, width=400, height=350'
                );*/

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

            getFixedAmount: function() {
                return window.checkoutConfig.payment.splitit_payment.fixedamount;
            },

            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);
