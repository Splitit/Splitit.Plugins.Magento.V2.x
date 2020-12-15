var jqueryInterval = setInterval(function(){

    if(window.jQuery){
      clearInterval(jqueryInterval);
      //tell me more button
	    jQuery(document).on('click', '#tell-me-more', function(e){

	        e.preventDefault();
	        var left = (screen.width - 433)/2;
	 		 var top = (screen.height/2)-(window.innerHeight/2);
	        var win= window.open(jQuery(this).attr('href'),"Tell me more","width=433,height=607,left="+left+",top="+top+",location=no,status=no,scrollbars=no,resizable=no");
	        win.document.writeln("<body style='margin:0px'><img width=100% src='"+jQuery(this).attr('href')+"' />");
	        win.document.writeln("</body>");
	        win.document.write('<title>Splitit Learn More</title>');

	        return;
	    });
      runMyScripts();
     }else{

     }
  }, 1000);

function runMyScripts(){
	var productId = '';
	if(jQuery('#product_addtocart_form').length && jQuery('#product_addtocart_form input[name="product"]').length){
		productId = jQuery('#product_addtocart_form input[name="product"]').val();
	}
	jQuery.ajax({
			url: SPLITIT_BASE_URL + "splititpaymentmethod/showinstallmentprice/getinstallmentprice",
		data : { pid : productId},
		success: function(result){

			var numOfInstallmentForDisplay = result.numOfInstallmentForDisplay;
			var splititpaymentmethod = jQuery("#splitit-paymentmethod");
				// show help link
				if(result.help.splitit_paymentmethod.link != undefined){
					if(splititpaymentmethod.find('a').length){
						splititpaymentmethod.find('a').remove();
					}
					var helpLink = '<a style="float: none;" href="javascript:void(0);" onclick="popWin(\'' +result.help.splitit_paymentmethod.link + '\',\'' +  result.help.splitit_paymentmethod.title + '\')">'+result.help.splitit_paymentmethod.title+'</a>';

					splititpaymentmethod.append(helpLink);
				}
			// show help link
			if(result.help.splitit_paymentredirect.link != undefined){
				if(jQuery("#splitit-paymentredirect").find('a').length){
					jQuery("#splitit-paymentredirect").find('a').remove();
				}
				var helpLink = '<a style="float: none;" href="javascript:void(0);" onclick="popWin(\'' +result.help.splitit_paymentredirect.link + '\',\'' +  result.help.splitit_paymentredirect.title + '\')">'+result.help.splitit_paymentredirect.title+'</a>';

				jQuery("#splitit-paymentredirect").append(helpLink);
			}
			if(result.isActive){
				var priceSpan = "";
				var productprice = "";
				var installments = 0;
				var currencySymbol = "";
				var installmentNewSpan = "";
				var displayInstallmentPriceOnPage = result.displayInstallmentPriceOnPage;
				// for category page only
				if(jQuery('.product-items').length && displayInstallmentPriceOnPage.indexOf("category") >= 0){
					jQuery(".product-items li").each(function(){
						priceSpan = jQuery(this).find(".price");
						productprice = jQuery(priceSpan).text();
						currencySymbol = result.currencySymbol;
						productprice = Number(productprice.replace(/[^0-9\.]+/g,""));
						productprice = jQuery(this).find('[data-price-type="finalPrice"]').attr('data-price-amount');
						installments = (productprice/result.numOfInstallmentForDisplay).toFixed(2);
						installmentNewSpan = '<br><span class="cart-installment">'+'<span class="after-ins-price">'+currencySymbol+installments+'</span>'+' x '+result.numOfInstallmentForDisplay+' '+result.installmetPriceText+'</span>';
						jQuery(priceSpan).after(installmentNewSpan);

					});
				}
				// for product detail page
				if(jQuery('.product-info-price').length && displayInstallmentPriceOnPage.indexOf("product") >= 0){
					priceSpan = jQuery(".product-info-price").find(".price");
					productprice = jQuery(priceSpan).text();
					currencySymbol = result.currencySymbol;
					productprice = Number(productprice.replace(/[^0-9\.]+/g,""));
					productprice = jQuery(".product-info-price").find('[data-price-type="finalPrice"]').attr('data-price-amount');
					installments = (productprice/result.numOfInstallmentForDisplay).toFixed(2);
					window.splitit_product_price = productprice;
					window.splitit_installments = result.numOfInstallmentForDisplay;
					window.splitit_currency = currencySymbol;
					installmentNewSpan = result.installmetPriceText.replace('{AMOUNT}','<span class="after-ins-price">'+currencySymbol+installments+'</span>');
					installmentNewSpan = '<br><span class="cart-installment">'+installmentNewSpan+'</span>';
					jQuery('.product-info-price').after(installmentNewSpan);

				}
				// for cart page only
				if((window.location.href).indexOf("checkout/cart") >= 0 && displayInstallmentPriceOnPage.indexOf("cart") >= 0){

					var cartPageInterval = setInterval(function(){
		    		if(jQuery("table.totals").length){
		    			clearInterval(cartPageInterval);
						productprice = result.grandTotal;
						currencySymbol = result.currencySymbol;
						productprice = Number(productprice.replace(/[^0-9\.]+/g,""));
						installments = (productprice/result.numOfInstallmentForDisplay).toFixed(2);
						installmentNewSpan = result.installmetPriceText.replace('{AMOUNT}','<span class="after-ins-price">'+currencySymbol+installments+'</span>');
					installmentNewSpan = '<br><span class="cart-installment">'+installmentNewSpan+'</span>';
						jQuery('table.totals tr:last').after('<tr><td>'+installmentNewSpan+'</td></tr>');
		    		}else{

		    		}

			      }, 3000);



				}
				// onepage checkout only
				if( (window.location.href).indexOf("checkout") >= 0 && (window.location.href).indexOf("checkout/cart") < 0 &&  displayInstallmentPriceOnPage.indexOf("checkout") >= 0){

					var checkoutOnepageInterval = setInterval(function(){
						if(jQuery("div.iwd-grand-total-item").length){
							clearInterval(checkoutOnepageInterval);
							productprice = result.grandTotal;
							currencySymbol = result.currencySymbol;
							productprice = Number(productprice.replace(/[^0-9\.]+/g,""));
							installments = (productprice/result.numOfInstallmentForDisplay).toFixed(2);
							installmentNewSpan = result.installmetPriceText.replace('{AMOUNT}','<span class="after-ins-price">'+currencySymbol+installments+'</span>');
							installmentNewSpan = '<br><span class="cart-installment">'+installmentNewSpan+'</span>';
							jQuery('div.iwd-grand-total-item').after(installmentNewSpan);
						}
					}, 3000);


				}

			}

		}
	});

	// regular checkout page


    if((window.location.href).indexOf("checkout") >= 0 && (window.location.href).indexOf("checkout/cart") < 0){
    	var hashInterval = setInterval(function(){
    		if(jQuery("table.table-totals").length){
    			clearInterval(hashInterval);
			    runMyScriptForCheckout();
    		}else{
    		}

	      }, 3000);
	     }else{
	     }

}

function runMyScriptForCheckout(){
	if (document.getElementById('splitit_paymentmethod')!=undefined || document.getElementById('splitit_paymentredirect')!=undefined){
	jQuery.ajax({
			url: SPLITIT_BASE_URL + "splititpaymentmethod/showinstallmentprice/getinstallmentprice",
		success: function(result){

			var numOfInstallmentForDisplay = result.numOfInstallmentForDisplay;
			var splititpaymentmethod = jQuery("#splitit-paymentmethod");
				// show help link
				if(result.help.splitit_paymentmethod.link != undefined){
					if(splititpaymentmethod.find('a').length){
						splititpaymentmethod.find('a').remove();
					}
					var helpLink = '<a style="float: none;" href="javascript:void(0);" onclick="popWin(\'' +result.help.splitit_paymentmethod.link + '\',\'' +  result.help.splitit_paymentmethod.title + '\')">'+result.help.splitit_paymentmethod.title+'</a>';

					splititpaymentmethod.append(helpLink);
				}
			// show help link
			if(result.help.splitit_paymentredirect.link != undefined){
				if(jQuery("#splitit-paymentredirect").find('a').length){
					jQuery("#splitit-paymentredirect").find('a').remove();
				}
				var helpLink = '<a style="float: none;" href="javascript:void(0);" onclick="popWin(\'' +result.help.splitit_paymentredirect.link + '\',\'' +  result.help.splitit_paymentredirect.title + '\')">'+result.help.splitit_paymentredirect.title+'</a>';

				jQuery("#splitit-paymentredirect").append(helpLink);
			}
			if(result.isActive){
				var priceSpan = "";
				var productprice = "";
				var installments = 0;
				var currencySymbol = "";
				var installmentNewSpan = "";
				var displayInstallmentPriceOnPage = result.displayInstallmentPriceOnPage;

				// onepage checkout only
				if(jQuery("table.table-totals").length && displayInstallmentPriceOnPage.indexOf("checkout") >= 0){
					productprice = result.grandTotal;
					currencySymbol = result.currencySymbol;
					productprice = Number(productprice.replace(/[^0-9\.]+/g,""));
					installments = (productprice/result.numOfInstallmentForDisplay).toFixed(2);
					installmentNewSpan = result.installmetPriceText.replace('{AMOUNT}','<span class="after-ins-price">'+currencySymbol+installments+'</span>');
					installmentNewSpan = '<br><span class="cart-installment">'+installmentNewSpan+'</span>';
					jQuery('.cart-installment').closest('tr').remove();
					jQuery('table.table-totals').find('.cart-installment-onepage').closest('tr').remove();
					jQuery('table.table-totals tr:last').after('<tr><td>'+installmentNewSpan+'</td></tr>');

				}

			}

		}
	});
	}
}
