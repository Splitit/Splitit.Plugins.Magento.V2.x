window.onload = function(){
	
	var splititAvail = 0;
	var curUrl      = window.location.href; 
	var baseUrl = "";
	baseUrl = curUrl.substring(0, curUrl.indexOf('checkout'));
	/*jQuery(document).on('change', '#inchoo_stripe', function(){
		console.log("---sfsfsfsf");
		getInstallmentOptions();
	}); */
	var sitInterval = setInterval(function(){
		splititAvail = jQuery('#inchoo_stripe').length;
		console.log(splititAvail);
		if(splititAvail > 0){
			getInstallmentOptions();
			clearInterval(sitInterval);
		}	
	}, 2000);

	function getInstallmentOptions(){
		jQuery.ajax({
			url: baseUrl + "stripepayment/installments/getinstallment", 
			success: function(result){

			jQuery("#select-num-of-installments").html(jQuery.parseJSON(result));
			// disable place order button
			jQuery("button#splitit-form").prop("disabled",true);
			
			}
		});
	}

	jQuery(document).on("click", ".apr-tc",function(){
		var selectedInstallment = jQuery("#select-num-of-installments").val();
		var ccNum = jQuery("form.splitit-form").find("input[name='payment[cc_number]']").val();
		var ccExpMonth = jQuery("form.splitit-form").find("select[name='payment[cc_exp_month]']").val();
		var ccExpYear = jQuery("form.splitit-form").find("select[name='payment[cc_exp_year]']").val();
		var ccCvv = jQuery("form.splitit-form").find("input[name='payment[cc_cid]']").val();
		
		
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
			url: baseUrl + "stripepayment/installmentplaninit/installmentplaninit", 
			type : 'POST',
	        dataType:'json',
	        data:{"selectedInstallment":selectedInstallment},
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
		
