define([
    'jquery'
], function ($) {
    'use strict';

    return function (widget) {

        $.widget('mage.SwatchRenderer', widget, {
            _UpdatePrice: function () {
                var prices = this._getNewPrices();
                var newPrice = {detail: prices.finalPrice.amount};
                var oldPrice = window.splitit_product_price;
                if (oldPrice != undefined) {
                    var installments = (newPrice.detail/parseFloat(window.splitit_installments)).toFixed(2);
                    var newValue = window.splitit_currency + installments;
                    jQuery('.cart-installment .after-ins-price').html(newValue);
                }
                return this._super();
            }
        });

        return $.mage.SwatchRenderer;
    }
});
