
var config = {
    map: {
        '*': {
            'Magento_Checkout/js/action/select-payment-method':
                'Splitit_Paymentmethod/js/action/payment/select-payment-method'
        }
    },
    config: {
        mixins: {
            'Magento_Swatches/js/swatch-renderer': {
                'Splitit_Paymentmethod/js/swatch-renderer-mixin': true
            }
        }
    }
};
