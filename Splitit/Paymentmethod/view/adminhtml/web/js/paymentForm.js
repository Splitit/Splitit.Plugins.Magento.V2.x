var baseCurrencyCode1 = "";
var baseCurrencySymbol1 = "";
var currencyCodeSymbol1 = "";
var jqueryIsHere1 = 0;
var curUrl1 = window.location.href; 
var baseUrl1 = "";
baseUrl1 = window.location.origin+'/';

var jqueryInterval1 = setInterval(function(){  
//    var dependingOnCart = document.getElementById('payment_us_splitit_paymentredirect_depending_on_cart_total');  
    var dependingOnCart = document.getElementsByName('groups[splitit_paymentredirect][fields][depending_on_cart_total][value]')[0];  
    if (typeof jQuery != 'undefined'){
      if(dependingOnCart){
        jqueryIsHere1 = 1;
        clearInterval(jqueryInterval1);      
        runMyScripts1();     
      }else{
        //console.log('Element not found!!');
      }
    } else {
      //console.log("jQuery not found.")
    }
  }, 1000);

var productListInterval1 = setInterval(function(){
  var prodList1 = document.getElementsByName('groups[splitit_paymentredirect][fields][splitit_product_skus][value]')[0];
  if((typeof prodList1 != 'undefined')&&prodList1){
    clearInterval(productListInterval1);
    jQuery(document).on('click','.close-btn-prod-list1',function(){
        //console.log('prod1 remove clicked');
        var $elemId1 = jQuery('#'+jQuery(prodList1).attr('id')+'_prodlist');
        var prodId1 = jQuery(this).parent().attr('data-proid');
        var terms1 = split( $elemId1.val() );
        terms1 = terms1.filter(function(v){return v!==''});
        var index1 = terms1.indexOf(prodId1);
        if (index1 > -1) {
          terms1.splice(index1, 1);
        }
        $elemId1.val(terms1.join(","));
        jQuery(this).parent().remove();
    });
    autoPopulateProds1(prodList1);
    autoCompleteWizard1(prodList1);
  }
},2000);

function autoPopulateProds1(prodList1){
  var prodIds1 = jQuery(prodList1).val();
  jQuery.ajax({
    url: baseUrl + "splititpaymentmethod/index/productlist",
    data: {isAjax: 1, prodIds: prodIds1},
    type: 'POST',
    dataType: 'json',
    success: function(result1){
      //console.log(result1);
      result1.forEach(function(ash1){
        jQuery('<div class="search-item-box" title="'+ash1.label+'" data-proid="'+ash1.value+'">'
          +ash1.label+'<span class="close-btn-prod-list1"></span</div>')
        .appendTo('.selected-item-conatiner1');
      });
    }
  });
  jQuery(prodList1).val('');
}

function autoCompleteWizard1(prodList1){
  var gutterWidth1 = 8, itemPerColumn1 = 4;
  var $prod1 = jQuery(prodList1);
  $prod1.attr('placeholder','Product Name/SKU');
  $prod1.wrapAll('<div class="ui-widget-prod-list1"></div>');
  var eleHtml1 = $prod1.parent().html();
  var textId1 = $prod1.attr('id');
  $prod1.attr('name','').parent().append('<div class="selected-item-conatiner1"></div>').append(jQuery(eleHtml1).attr('type','hidden').attr('id',textId1+"_prodlist"));
  $prod1.on( "keydown", function( event1 ) {
    if ( event1.keyCode === jQuery.ui.keyCode.TAB &&
        jQuery( this ).autocomplete().data("uiAutocomplete").menu.active ) {
      event1.preventDefault();
    }
  })
  .autocomplete({
    minLength: 3,
    source: function( request1, response1 ) {
      jQuery.getJSON( baseUrl + "splititpaymentmethod/index/productlist", {
        term: extractLast( request1.term )
      }, response1 );
    },
    search: function() {
      // custom minLength
      var term1 = extractLast( this.value );
      if ( term1.length < 3 ) {
        return false;
      }
    },
    focus: function() {
      // prevent value inserted on focus
      return false;
    },
    select: function( event, ui ) {
      var terms1 = split( jQuery('#'+textId1+"_prodlist").val() );
      terms1 = terms1.filter(function(v){return v!==''});
      // remove the current input
      // terms.pop();
      // add the selected item
      // terms.push( ui.item.value );
      if(jQuery.inArray(ui.item.value,terms1)==-1){
        terms1.push( ui.item.value );
        var itemBoxWidth1 = jQuery(this).outerWidth() / itemPerColumn1;
        jQuery('<div class="search-item-box" title="'+ui.item.label+'" data-proid="'+ui.item.value+'">'+ui.item.label+'<span class="close-btn-prod-list1"></span</div>')
        .appendTo('.selected-item-conatiner1');
      }
        jQuery('.ui-helper-hidden-accessible').text('');
      // add placeholder to get the comma-and-space at the end
      // terms.push( "" );
      // this.value = terms.join( ", " );
      jQuery('#'+textId1+"_prodlist").val(terms1.join(","));
      this.value = '';      
      return false;
    }
  });
}

function runMyScripts1(){

  // run on page load.  
  getCurrency1();
  

  jQuery(document).on("click", "#save", function(event) {
//alert("paymentForm");
      var flag1P = 0;
      var flag2P = 0;
      var percentageFlagP = 0;
      var overlapsP = 0;
      var fromBiggerP = 0;
      var hasGapP = 0;
    
      // validation for depanding on cart
      // $(this).unbind('click');
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      //alert('Please fill the required fields in Splitit section "Depending on cart total"');
      jQuery("[id^=payment_][id$=_splitit_paymentredirect_percentage_of_order]:first").css("border","1px solid #ccc");
      if(jQuery('[id^=payment_][id$=_splitit_paymentredirect_select_installment_setup]:first').val() == 'depending_on_cart_total'){
        var fromToArrP = {};
        var iP=0;
        jQuery("#tier_price_containerP tr").each(function(){
        
        
        var doctv_fromP = parseFloat(jQuery(this).find(".doctv_from").val());
        var doctv_toP = parseFloat(jQuery(this).find(".doctv_to").val());
        var doctv_currencyP = jQuery(this).find(".doctv_currency").val();

        jQuery(this).find(".doctv_from").css("border","1px solid #ccc");
        jQuery(this).find(".doctv_to").css("border","1px solid #ccc");
        jQuery(this).find("select.doctv_installments").css("border","1px solid #ccc");
        // validation for installments
        var installmentsCountP = jQuery(this).find("select.doctv_installments  :selected").length;
        if(installmentsCountP == 0){
          jQuery(this).find("select.doctv_installments").css("border-color","red");
          flag1P++;
           //return false;
        }
        // validation for from and to amount
        if((doctv_fromP == "" || isNaN(doctv_fromP)) && (doctv_toP == "" || isNaN(doctv_toP))){
          // all empty and string
          jQuery(this).find(".doctv_from").css("border","1px solid red");
          jQuery(this).find(".doctv_to").css("border","1px solid red");
          flag1P++;
           //return false;
          
          
        }else if(doctv_fromP != "" || isNaN(doctv_fromP)){
          if( doctv_toP == "" || isNaN(doctv_toP)){
            // check from less than 1000 and to is empty
            if(doctv_fromP < 1000){
              jQuery(this).find(".doctv_to").css("border","1px solid red");
              flag1P++;
               //return false;
            }
          }
          if(doctv_fromP == "" || isNaN(doctv_fromP)){
              // when from empty
              jQuery(this).find(".doctv_from").css("border"," 1px solid red");
              flag1P++;
              //return false;
          } 
          
        }

        //  validation that there are no overlaps with the periods
        if (!fromToArrP.hasOwnProperty(doctv_currencyP)) {
          fromToArrP[doctv_currencyP] = {};  
        }
        var countObjP = Object.keys(fromToArrP[doctv_currencyP]).length;
        fromToArrP[doctv_currencyP][countObjP] = {};
        fromToArrP[doctv_currencyP][countObjP]["from"] = doctv_fromP;
        fromToArrP[doctv_currencyP][countObjP]["to"] = doctv_toP;
        fromToArrP[doctv_currencyP][countObjP]["currency"] = doctv_currencyP;
        if(flag1P == 0 && Object.keys(fromToArrP[doctv_currencyP]).length > 1){
          for(var jP=0; jP<Object.keys(fromToArrP[doctv_currencyP]).length-1; jP++){
             if(((doctv_fromP >= fromToArrP[doctv_currencyP][jP]["from"] && doctv_fromP <= fromToArrP[doctv_currencyP][jP]["to"]) || (doctv_toP >= fromToArrP[doctv_currencyP][jP]["from"] && doctv_toP <= fromToArrP[doctv_currencyP][jP]["to"])) && doctv_currencyP == fromToArrP[doctv_currencyP][jP]["currency"]){
              //console.log("forrrr");
              jQuery(this).find(".doctv_from").css("border","1px solid red");
              jQuery(this).find(".doctv_to").css("border","1px solid red");
              flag1P++;
              overlapsP++;
            }
            // check if there is gap between previous to and next from
            if(((fromToArrP[doctv_currencyP][jP]["to"]+1) != fromToArrP[doctv_currencyP][jP+1]["from"]) && doctv_currencyP == fromToArrP[doctv_currencyP][jP]["currency"]){
              jQuery(this).find(".doctv_from").css("border","1px solid red");
              jQuery(this).find(".doctv_to").css("border","1px solid red");
              flag1P++;
              hasGapP++;  
            } 
          }
        }

        iP++;

        // check if from is bigger than to
        if(doctv_fromP > doctv_toP){
          jQuery(this).find(".doctv_from").css("border","1px solid red");
          jQuery(this).find(".doctv_to").css("border","1px solid red");  
          fromBiggerP++;
        }

        });
      }

      // 
      if(jQuery("select[id^=payment_][id$=_splitit_paymentredirect_first_payment]:first").val() == "percentage"){
        var percentageOfOrderP = jQuery("[id^=payment_][id$=_splitit_paymentredirect_percentage_of_order]:first").val();
        if(percentageOfOrderP > 50 ){
          percentageFlagP++; 
          flag1P++; 
        }
        
      }
//      alert(jQuery('#payment_us_splitit_paymentredirect_select_installment_setup').val());
      if(flag1P == 0){
        if(jQuery('[id^=payment_][id$=_splitit_paymentredirect_select_installment_setup]:first').val() == 'depending_on_cart_total'){
          createJsonOfDependingOnCartTotal1(); 
        }
        /*eval(configForm.submit());*/
      }else{
        if(fromBiggerP){
          alert("From amount should be lesser than To.");
        }
        else if(overlapsP){
          alert("From and To amount should not Overlap");
        }
        else if(percentageFlagP){
          jQuery("#payment_pis_cc_percentage_of_order").css("border","1px solid red");
          alert("Please enter Percentage of order % <= 50");
        }else if(hasGapP){
          alert("There should not be Gap between To and From amounts.");
        }else{
          alert('Please fill the required fields in Splitit section "Depending on cart total"');  
        }
        
         return false;
      }
       

    });

  

  jQuery(document).on('change', '[id^=payment_][id$=_splitit_paymentredirect_select_installment_setup]:first', function(){
    if(jQuery(this).val() == 'fixed'){
      jQuery('#tiers_tableP.splitit').closest('td').addClass('not-allowed-td');
      jQuery('[id^=payment_][id$=_splitit_paymentredirect_fixed_installment]:first').removeAttr('disabled');
    }else{
      jQuery('#tiers_tableP.splitit').closest('td').removeClass('not-allowed-td');
      jQuery('[id^=payment_][id$=_splitit_paymentredirect_fixed_installment]:first').attr('disabled', 'disabled');
    }
  });

  jQuery(document).on("click","[id^=payment_][id$=_splitit_paymentredirect_check_setting]:first", function(){
    checkSetting1();  
  });
}

function getCurrency1(){
  
  
  jQuery.ajax({
    url: baseUrl1 + "splititpaymentmethod/getcurrency/getcurrency", 
    showLoader: true,
    success: function(result){
      jQuery('#tiers_tableP.splitit').find("input.doctv_currency").val(result.currencyCode);
      jQuery('#tiers_tableP.splitit').find("span.base-currency-symbol").html(result.currencySymbol);
      baseCurrencyCode1 = result.currencyCode;
      baseCurrencySymbol1 = result.currencySymbol;
      currencyCodeSymbol1 = result.currencyCodeSymbol;

      // console.log(jQuery("#payment_us_splitit_paymentredirect_depending_on_cart_total").length);
      // show depanding on cart table
      var tableHtmlP = getTableHtml1();
//      jQuery("#payment_us_splitit_paymentredirect_depending_on_cart_total").replaceWith(tableHtmlP);
      jQuery('[name="groups[splitit_paymentredirect][fields][depending_on_cart_total][value]"]').replaceWith(tableHtmlP);
      // disable or enable Fixed and Depanding on cart total
      if(jQuery("[id^=payment_][id$=_splitit_paymentredirect_select_installment_setup]:first").val() == 'fixed'){
        jQuery('#tiers_tableP.splitit').closest('td').addClass('not-allowed-td');
        jQuery('[id^=payment_][id$=_splitit_paymentredirect_fixed_installment]:first').removeAttr('disabled');
      }else{
        jQuery('#tiers_tableP.splitit').closest('td').removeClass('not-allowed-td');
        jQuery('[id^=payment_][id$=_splitit_paymentredirect_fixed_installment]:first').attr('disabled', 'disabled');
      }
    }
  });
}

function getTableHtml1(){
  var htmlP = '<table class="data border splitit" id="tiers_tableP" cellspacing="0" border="1">'
        +'<div class="tiers_tableP_overlay"></div>'
        +'<thead>'
        +'<tr class="headings">'
        +'<th style="min-width: 100px;">Cart total</th>'
        +'<th style="min-width: 160px;">#Installments</th>'
        +'<th >Currency</th>'
        +'<th class="last">Action</th>'
        +'</tr>'
        +'</thead>'
        +'<tbody id="tier_price_containerP">';
    htmlP += getTableInnerContent1();    
    htmlP += '</tbody>'
        +'<tfoot>'
        +'<tr>'
        +'<td style="display:none"></td>'
        +'<td colspan="4" align="right"><button id="" title="Add Tier" type="button" class="scalable add add-tier" onclick="addRow1();" style=""><span><span><span>Add Tier</span></span></span></button></td>'
        +'</tr>'
        +'</tfoot>'
        +'</table>';
    return htmlP;
}

function getTableInnerContent1(){
  var jsonValueP = jQuery("[id^=payment_][id$=_splitit_paymentredirect_depanding_on_cart_total_values]:first").val();
  //console.log(jsonValueP);
  if(jsonValueP == "" || jsonValueP == undefined){
    return getRowHtml1();
  }else{
    return getRowHtmlFromJson1();
  }
}

function getRowHtml1(){
  var rowHtmlP = '<tr>'
         +'<td style="padding: 8px;">'
          +'From<br><span class="base-currency-symbol">'+getCurrencyCode1("")+'</span><input type="text" class="doctv_from" name="doctv_from_pf" /><br>To<br><span class="base-currency-symbol">'+getCurrencyCode1("")+'</span><input type="text" class="doctv_to" name="doctv_to_pf" />'
         +'</td>'
         +'<td style="padding: 8px;">'
          +'<select id="doctv_installments" name="doctv_installments_pf" class=" select multiselect doctv_installments" size="10" multiple="multiple">';
            for(var iP=1; iP<=24; iP++){
          rowHtmlP += '<option value="'+iP+'">'+iP+' Installments</option>';
      }
            rowHtmlP+='</select>'
         +'</td>'
         +'<td style="padding: 8px; text-align: center;">'
           +getCurrencyDropdown1("")
         +'</td>'
         +'<td style="padding: 8px; text-align: center;">'
          +'<button title="Delete Tier" type="button" class="scalable delete icon-btn delete-product-option" id="" onclick="deleteRow1(this);"><span><span><span>Delete</span></span></span></button>'
         +'</td>'
      +'</tr>';

    return rowHtmlP;  
}

function getRowHtmlFromJson1(){
  var doctvP = JSON.parse(jQuery("[id^=payment_][id$=_splitit_paymentredirect_depanding_on_cart_total_values]:first").val());
  var rowHtmlP = "";
  jQuery.each( doctvP, function( indexP, valueP ){
      rowHtmlP += '<tr>';
        rowHtmlP += '<td style="padding: 8px;"> From<br><span class="base-currency-symbol">'+getCurrencyCode1(valueP.doctv.currency)+'</span><input type="text" class="doctv_from" name="doctv_from_pf" value="'+valueP.doctv.from+'" /><br>To<br><span class="base-currency-symbol">'+getCurrencyCode1(valueP.doctv.currency)+'</span><input type="text" class="doctv_to" name="doctv_to_pf" value="'+valueP.doctv.to+'" /> </td>';
        rowHtmlP += '<td style="padding: 8px;"> <select id="" name="doctv_installments_pf" class=" select multiselect doctv_installments" size="10" multiple="multiple">';
        var installmentsP = valueP.doctv.installments.split(',');
        var iP = 2;
        var selectedP = "";
        for(iP=1; iP<=24; iP++){
          if(jQuery.inArray(iP.toString(), installmentsP) != -1){
            selectedP = 'selected="selected"';
          }
          rowHtmlP += '<option value="'+iP+'" '+selectedP+'>'+iP+' Installments</option>';  
            selectedP = "";

        }
        rowHtmlP += '</select></td>';
        rowHtmlP += '<td style="padding: 8px; text-align: center;">'+getCurrencyDropdown1(valueP.doctv.currency)+'</td>'; 
        rowHtmlP += '<td style="padding: 8px; text-align: center;">'
                +'<button title="Delete Tier" type="button" class="scalable delete icon-btn delete-product-option" id="" onclick="deleteRow1(this);"><span><span><span>Delete</span></span></span></button>'
              +'</td>'
              +'</tr>';

  });
  return rowHtmlP;
}

function addRow1(){
  var rowHtmlP = getRowHtml1();
  jQuery("#tiers_tableP.splitit tbody#tier_price_containerP").append(rowHtmlP);
}
function deleteRow1(curObjP){
    var countP = jQuery(curObjP).closest("tbody").find("tr").length;
    if(countP > 1){
        jQuery(curObjP).closest("tr").remove();    
    }
    
}
function getCurrencyDropdown1(selectedCurrencyP){
    var currencyDropdownP = '<select id="" class="doctv_currency" name="doctv_currency_pf" class=" select">';
    var selectedP = "";       
    jQuery.each(currencyCodeSymbol1, function(iP, valP) {
      selectedP = ""
        if(selectedCurrencyP == iP){
            selectedP = 'selected="selected"';
        }
        currencyDropdownP += '<option value="'+iP+'" '+selectedP+'>'+iP+'</option>';    
    });
    return currencyDropdownP += '</select>';

}

function getCurrencyCode1(selectedCurrencyP){
    var currencyCodeP = "";

    if(selectedCurrencyP == ""){
        var flagP = 0;
        jQuery.each(currencyCodeSymbol1, function(iP, valP) {
            if(flagP == 0){
              currencyCodeP = valP;
            }
            flagP++;
        });
        return currencyCodeP;

    }
    if (currencyCodeSymbol1.hasOwnProperty(selectedCurrencyP)){
        return currencyCodeSymbol1[selectedCurrencyP];
    } 
}

function createJsonOfDependingOnCartTotal1(){
     var iP = 0;
     var objectP = {};

     jQuery("#tiers_tableP.splitit tbody").find('tr').each(function() {
        var $thisP = jQuery(this);
        var installmentsP = [];
        objectP[iP] = {};
        objectP[iP]["doctv"] = {};
        objectP[iP]["doctv"]["from"] = $thisP.find("td:first-child").find("input.doctv_from").val();
        objectP[iP]["doctv"]["to"] = $thisP.find("td:first-child").find("input.doctv_to").val(); 
        $thisP.find("td:nth-child(2)").find(".doctv_installments :selected").each(function(iP, selectedP){ 
           installmentsP.push(jQuery(selectedP).val());
        });
        objectP[iP]["doctv"]["installments"] = installmentsP.toString();
        objectP[iP]["doctv"]["currency"] = $thisP.find("td:nth-child(3)").find(".doctv_currency").val();
       
        iP++;      
    });
    objectP = JSON.stringify(objectP);
    //console.log(objectP);
    jQuery("[id^=payment_][id$=_splitit_paymentredirect_depanding_on_cart_total_values]:first").val(objectP);

}

function checkSetting1(){

  jQuery.ajax({
    url: baseUrl1 + "splititpaymentmethod/checksetting/checksetting", 
    type: 'post',
    dataType: "json",
    data: {method:'splitit_paymentredirect'},
    showLoader: true,
    success: function(result){
      if(result.status){
        alert(result.successMsg);  
      } else{
        alert(result.errorMsg);
      } 
    }
  });
}