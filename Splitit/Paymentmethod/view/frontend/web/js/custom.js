window.onload = function(){
	
	var splititAvail = 0;
	/*var curUrl      = window.location.href; 
	var baseUrl = "";

	baseUrl = curUrl.substring(0, curUrl.indexOf('checkout'));*/
	var url = window.location.hostname;
	var http = window.location.protocol;
	var baseUrl = http+"//"+url+"/";
	
	/*var sitInterval = setInterval(function(){
		splititAvail = jQuery('#splitit_paymentmethod').length;
		console.log(splititAvail);
		if(splititAvail > 0){
			// get num of installment and help link url if active in configuration
			//getInstallmentOptions();
			clearInterval(sitInterval);
		}	
	}, 2000);*/
	jQuery(document).on("focus", "form.splitit-form input, form.splitit-form select",function(){
		var numOfInstallmentLength = jQuery("select#select-num-of-installments option").length;
		if(numOfInstallmentLength == 1){
			getInstallmentOptions();
		}
	});
	
        jQuery(document).on('click','input[type="radio"]',function(e){
            jQuery('.table-totals tbody').find('tr.totals.splititfee').remove();
            runMyScriptForCheckout();
        });
        jQuery(document).ready(function(){
        	var interval=setInterval(function(){
            if(!jQuery('#pageReloaded').length){
                jQuery('#splitit_paymentmethod').parent().append('<input type="hidden" id="pageReloaded" value="1"/>');
            }
        	if(jQuery('#splitit_paymentmethod,#splitit_paymentredirect').is(':checked')){                    
        		splititFees(jQuery('#select-num-of-installments').val());
        		runMyScriptForCheckout();
        		clearInterval(interval);
        	}
        	},1000);
        });
        jQuery(document).on('click','#splitit_paymentmethod,#splitit_paymentredirect',function(e){
            splititFees(jQuery('#select-num-of-installments').val());
            runMyScriptForCheckout();
        });
        jQuery(document).on('change','#select-num-of-installments',function(e){
            splititFees(jQuery('#select-num-of-installments').val());
            jQuery('#pageReloaded').val('0');
            setTimeout(function(){
                jQuery('#splitit_paymentmethod').trigger('click');                
            },1000);
        });

        function splititFees(selectedIns){
        	jQuery.ajax({
        		url: baseUrl + "splititpaymentmethod/index/update",
                        data : {selectedIns:selectedIns},
        		showLoader: true,
        		success: function(result){
        			if(result.success){
        				jQuery('.table-totals tbody').find('tr.totals.splititfee').remove();
        				jQuery('.table-totals tbody').find('tr.totals.sub').after('<tr class="totals fee splititfee excl"><th class="mark" colspan="1" scope="row">Splitit Fees</th><td class="amount"><span class="price" data-bind="text: getFormattedPrice()">'+result.data.splitit_fees+'</span></td></tr>');
        			}			
        		}
        	});
        }

	function getInstallmentOptions(){
		jQuery.ajax({
			url: baseUrl + "splititpaymentmethod/installments/getinstallment", 
			showLoader: true,
			success: function(result){
				
				
			// show help link
			/*if(result.helpSection.link != undefined){
				var helpLink = '<a style="float: none;" href="javascript:void(0);" onclick="popWin(\'' +result.helpSection.link + '\',\'' +  result.helpSection.title + '\')">'+result.helpSection.title+'</a>';
				
				jQuery("#splitit-paymentmethod").append(helpLink);	
			}*/
			

			// show installments
			jQuery("#select-num-of-installments").html(result.installmentHtml);
			// disable place order button
			jQuery("button#splitit-form").prop("disabled",true);
			
			if(result.installmentShow){
				jQuery("#select-num-of-installments").closest('.num-of-installments').show();
				jQuery('.apr-tc').show();
			} else {
				jQuery('.monthly-img').css('padding-top','1%');
				jQuery('.apr-tc').remove();
				jQuery("button#splitit-form").prop("disabled",false);
			}
			
			}
		});
	}


	jQuery(document).on("click", ".apr-tc",function(){
		var postcodeContainer = jQuery('div[name="shippingAddress.postcode"] input');
		var postcode = postcodeContainer.val();
		postcodeContainer.val('').change();
		postcodeContainer.val(postcode).change();

		setTimeout(function() {		// run init call after address is updated
			var selectedInstallment = jQuery("#select-num-of-installments").val();
			var ccNum = jQuery("form.splitit-form").find("input[name='payment[cc_number]']").val();
			var ccExpMonth = jQuery("form.splitit-form").find("select[name='payment[cc_exp_month]']").val();
			var ccExpYear = jQuery("form.splitit-form").find("select[name='payment[cc_exp_year]']").val();
			var ccCvv = jQuery("form.splitit-form").find("input[name='payment[cc_cid]']").val();
			var guestEmail = jQuery("input#customer-email").val();

			if(ccNum == ""){
				alert("Please input Credit card number");
				return;
			}
			if(ccExpMonth == ""){
				alert("Please select Expiration month");
				return;
			}
			if(ccExpYear == ""){
				alert("Please select Expiration year");
				return;
			}
			if(ccCvv == ""){
				alert("Please input Card verification number");
				return;
			}
			if(selectedInstallment == ""){
				alert("Please select Number of installments");
				return;
			}

			jQuery.ajax({
				url: baseUrl + "splititpaymentmethod/installmentplaninit/installmentplaninit",
				type : 'POST',
				dataType:'json',
				data:{"selectedInstallment":selectedInstallment, "guestEmail":guestEmail},
				showLoader: true,
				success: function(result){
					if(result.status){

						jQuery("#approval-popup").remove();
						jQuery(".approval-popup_ovelay").remove();
						jQuery('body').append(result.successMsg);
						//console.log(result.successMsg);
					}else{
						jQuery(".loading-mask").hide();
						alert(result.errorMsg);
					}
					//jQuery("#select-num-of-installments").html(jQuery.parseJSON(result));
				}});
		}, 3500);
	});
	// check on change of Number of Installments
	jQuery(document).on("change", "#select-num-of-installments", function(){
		// disable place order button
		jQuery("button#splitit-form").prop("disabled",true);
	});
	jQuery(document).on("click", ".approval-popup_ovelay", function(){
		jQuery("#approval-popup").remove();
		jQuery(".approval-popup_ovelay").remove();
	});
	jQuery(document).on("click", "#payment-schedule-link", function(){
		jQuery("#approval-popup").addClass("overflowHidden");
		jQuery('#payment-schedule, ._popup_overlay').show();
	});
	jQuery(document).on("click", "#complete-payment-schedule-close", function(){
		jQuery("#approval-popup").removeClass("overflowHidden");
		jQuery('#payment-schedule, ._popup_overlay').hide();	
	});
	jQuery(document).on("click", "#i_acknowledge_content_show", function(){
		jQuery("#approval-popup").addClass("overflowHidden");
		jQuery('#termAndConditionpopup, ._popup_overlay').show();		
	});
	jQuery(document).on("click", "#termAndConditionpopupCloseBtn", function(){
		jQuery("#approval-popup").removeClass("overflowHidden");
		jQuery('#termAndConditionpopup, ._popup_overlay').hide();	
	});
	// hide I acknowdge
    jQuery(document).on("click","#i_acknowledge",function(){
    	if(jQuery('#i_acknowledge').is(":checked")){
	    	jQuery(".i_ack_err").hide();
	    }else{
	    	jQuery(".i_ack_err").show();
	    }
    });


	
}

// close splitit popup when user check I agree
function paymentSave(){
    if(jQuery('#i_acknowledge').is(":checked")){
    	jQuery(".approval-popup_ovelay").hide();
    	// check term checkbox which is hidden
    	jQuery(".terms-conditions div").remove();
		jQuery('#pis_cc_terms').prop('checked', true);
		jQuery("#approval-popup").hide();
		// enable place order button
		jQuery("button#splitit-form").prop("disabled",false);		
    }else{
    	jQuery(".i_ack_err").show();
    }	

}
// close Approval popup
function closeApprovalPopup(){
	jQuery("#approval-popup, .approval-popup_ovelay").hide();
	jQuery("#approval-popup, .approval-popup_ovelay").remove();
}
		
function popWin(mylink, windowname) { 
	 	console.log('texdasft' );	    
	    var href;
	    href=mylink;
	    window.open(href, windowname, 'width=800,height=1075,scrollbars=yes,left=0,top=0,location=no,status=no,resizable=no'); 
	    return false; 
	  }
