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
                if (oldPrice != undefined && oldPrice != newPrice.detail) {
                    var installments = (newPrice.detail/parseFloat(window.splitit_installments)).toFixed(2);
                    var textBlock = jQuery('.cart-installment').html();
                    var newValue = window.splitit_currency + installments;
                    textBlock = textBlock.replace(/\$([0-9]+\.?[0-9]+)/, newValue);
                    jQuery('.cart-installment').html(textBlock);
                }
                return this._super();
            }
        });

        return $.mage.SwatchRenderer;
    }
});
