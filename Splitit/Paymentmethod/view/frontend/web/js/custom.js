window.onload = function(){

	window.changeIns = true;

	jQuery(document).ready(function(){
		jQuery('div.field.noi.required.num-of-installments').appendTo(jQuery('form fieldset.fieldset'));
		console.log('splitit_paymentmethod_cc_tnc==');
		console.log(jQuery('#splitit_paymentmethod_cc_tnc').attr('href'));
		console.log('splitit_paymentmethod_cc_privacy==');
		console.log(jQuery('#splitit_paymentmethod_cc_privacy').attr('href'));
		if(jQuery('#splitit_paymentmethod_cc_tnc').attr('href')=='javascript:void(0)' || jQuery('#splitit_paymentmethod_cc_privacy').attr('href')=='javascript:void(0)'){
			installmentPlanInitiate(false);
		}
	});
	
	
	jQuery(document).on("focus", "form.splitit-form input, form.splitit-form select",function(){
		var numOfInstallmentLength = jQuery("select#select-num-of-installments option").length;
		if(numOfInstallmentLength == 1){
			getInstallmentOptions();
		}
	});
	
        jQuery(document).on('click','input[type="radio"]',function(e){
            runMyScriptForCheckout();
            installmentPlanInitiate(false);
        });
        /*jQuery(document).ready(function(){
        	var interval=setInterval(function(){
            if(!jQuery('#pageReloaded').length){
                jQuery('#splitit_paymentmethod').parent().append('<input type="hidden" id="pageReloaded" value="1"/>');
            }
        	if(jQuery('#splitit_paymentmethod,#splitit_paymentredirect').is(':checked')){                    
        		runMyScriptForCheckout();
        		clearInterval(interval);
        	}
        	},1000);
        });
        jQuery(document).on('click','#splitit_paymentmethod,#splitit_paymentredirect',function(e){
            runMyScriptForCheckout();
        });
        jQuery(document).on('change','#select-num-of-installments',function(e){
            jQuery('#pageReloaded').val('0');
            setTimeout(function(){
                jQuery('#splitit_paymentmethod').trigger('click');                
            },1000);
        });*/

	function getInstallmentOptions(){
		if (document.getElementById('splitit_paymentmethod')!=undefined || document.getElementById('splitit_paymentredirect')!=undefined){
			jQuery.ajax({
				url: BASE_URL + "splititpaymentmethod/installments/getinstallment",
				showLoader: true,
				success: function(result){
				
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
	}
  
	jQuery(document).on("click", "#splitit-paymentmethod",function(){
		jQuery("input[name='payment[cc_number]").attr("maxlength","19");
	});

	function installmentPlanInitiate(validate=false,isOrder=false) {
		if(validate == undefined){
			validate=false;
		}
		var selectedInstallment = jQuery("#select-num-of-installments").val();
		var ccNum = jQuery("form.splitit-form").find("input[name='payment[cc_number]']").val();
		var ccExpMonth = jQuery("form.splitit-form").find("select[name='payment[cc_exp_month]']").val();
		var ccExpYear = jQuery("form.splitit-form").find("select[name='payment[cc_exp_year]']").val();
		var ccCvv = jQuery("form.splitit-form").find("input[name='payment[cc_cid]']").val();
		var guestEmail = jQuery("input#customer-email").val();

		if(validate){
			if(ccNum == ""){
				alert("Please input Credit card number");
				jQuery('#splitit_paymentmethod_cc').attr('checked', false);
				return;	
			}
			if(ccExpMonth == ""){
				alert("Please select Expiration month");
				jQuery('#splitit_paymentmethod_cc').attr('checked', false);
				return;	
			}
			if(ccExpYear == ""){
				alert("Please select Expiration year");
				jQuery('#splitit_paymentmethod_cc').attr('checked', false);
				return;	
			}
			if(ccCvv == ""){
				alert("Please input Card verification number");
				jQuery('#splitit_paymentmethod_cc').attr('checked', false);
				return;	
			}
			if(selectedInstallment == ""){
				alert("Please select Number of installments");
				jQuery('#splitit_paymentmethod_cc').attr('checked', false);
				return;
			}
		}
		if (document.getElementById('splitit_paymentmethod')!=undefined || document.getElementById('splitit_paymentredirect')!=undefined){
			jQuery.ajax({
				url: BASE_URL + "splititpaymentmethod/installmentplaninit/installmentplaninit",
				type : 'POST',
				dataType:'json',
				data:{"selectedInstallment":((selectedInstallment)?selectedInstallment:3), "guestEmail":guestEmail},
				showLoader: true,
				success: function(result){
						if(result.status){
							
							jQuery("#approval-popup").remove();
							jQuery(".approval-popup_ovelay").remove();
							jQuery('body').append(result.successMsg);
							if(!validate){
								var TnC_link = jQuery('div.termAndConditionBtn').find('a').first().attr('href');
								jQuery('#splitit_paymentmethod_cc_tnc').attr('href',TnC_link);
	
								var privacy_link = jQuery('div.termAndConditionBtn').find('a').last().attr('href');
								jQuery('#splitit_paymentmethod_cc_privacy').attr('href',privacy_link);
	
								/*jQuery("#approval-popup").removeClass("overflowHidden");
								jQuery('#termAndConditionpopup, ._popup_overlay').hide();*/
								closeApprovalPopup();
								controlOrderButton();
							} else if(isOrder){
								closeApprovalPopup();
								controlOrderButton();
							}
				
						} else {
							jQuery(".loading-mask").hide();
							alert(result.errorMsg);
						}
				
			}});
		}
	}

	jQuery(document).on("click", "button#splitit-form",function(){
		if(!jQuery('#splitit_paymentmethod_cc').is(":checked")){
			return false;
		}
		if(window.changeIns){
			installmentPlanInitiate(true,true);
		}
	});
	jQuery(document).on("click", ".apr-tc",function(){
		installmentPlanInitiate(true);
		window.changeIns = false;
	});
	// check on change of Number of Installments
	jQuery(document).on("change", "#select-num-of-installments", function(){
		// disable place order button
		// jQuery("button#splitit-form").prop("disabled",true);
		window.changeIns = true;
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

function controlOrderButton(){
	if(jQuery('#splitit_paymentmethod_cc').is(":checked")){
		/* enable place order button */
		jQuery("button#splitit-form").prop("disabled",false);
	} else {
		jQuery("button#splitit-form").prop("disabled",true);
	}
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
	 	var href;
	    href=mylink;
	    window.open(href, windowname, 'width=800,height=1075,scrollbars=yes,left=0,top=0,location=no,status=no,resizable=no'); 
	    return false; 
	  }
