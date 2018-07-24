var baseCurrencyCode = "";
var baseCurrencySymbol = "";
var currencyCodeSymbol = "";
var jqueryIsHere = 0;
var curUrl = window.location.href; 
var baseUrl = "";
baseUrl = window.location.origin+'/';

var jqueryInterval = setInterval(function(){  
//    var depandingOnCart = document.getElementById('payment_us_splitit_paymentmethod_depending_on_cart_total');  
    var depandingOnCart = document.getElementsByName('groups[splitit_paymentmethod][fields][depending_on_cart_total][value]')[0];  
    if(depandingOnCart){
      jqueryIsHere = 1;
      clearInterval(jqueryInterval);
//      splitit_fee_types();
splitit_fee_table();
      runMyScripts();
     }else{
      console.log('Element not found!!');
     }       
  }, 1000);

function runMyScripts(){

  // run on page load.  
  getCurrency();
  

  jQuery(document).on("mousedown", "#save", function(event) {

      var flag1 = 0;
      var flag2 = 0;
      var percentageFlag = 0;
      var overlaps = 0;
      var fromBigger = 0;
      var hasGap = 0;
    
      // validation for depanding on cart
      // $(this).unbind('click');
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      //alert('Please fill the required fields in Splitit section "Depending on cart total"');
      jQuery("[id^=payment_][id$=_splitit_paymentmethod_percentage_of_order]:first").css("border","1px solid #ccc");
      if(jQuery("[id^=payment_][id$=_splitit_paymentmethod_select_installment_setup]:first").val() == 'depending_on_cart_total'){
        var fromToArr = {};
        var i=0;
        jQuery("#tier_price_container tr").each(function(){
        
        
        var doctv_from = parseFloat(jQuery(this).find(".doctv_from").val());
        var doctv_to = parseFloat(jQuery(this).find(".doctv_to").val());
        var doctv_currency = jQuery(this).find(".doctv_currency").val();

        jQuery(this).find(".doctv_from").css("border","1px solid #ccc");
        jQuery(this).find(".doctv_to").css("border","1px solid #ccc");
        jQuery(this).find("select.doctv_installments").css("border","1px solid #ccc");
        // validation for installments
        var installmentsCount = jQuery(this).find("select.doctv_installments  :selected").length;
        if(installmentsCount == 0){
          jQuery(this).find("select.doctv_installments").css("border-color","red");
          flag1++;
           //return false;
        }
        // validation for from and to amount
        if((doctv_from == "" || isNaN(doctv_from)) && (doctv_to == "" || isNaN(doctv_to))){
          // all empty and string
          jQuery(this).find(".doctv_from").css("border","1px solid red");
          jQuery(this).find(".doctv_to").css("border","1px solid red");
          flag1++;
           //return false;
          
          
        }else if(doctv_from != "" || isNaN(doctv_from)){
          if( doctv_to == "" || isNaN(doctv_to)){
            // check from less than 1000 and to is empty
            if(doctv_from < 1000){
              jQuery(this).find(".doctv_to").css("border","1px solid red");
              flag1++;
               //return false;
            }
          }
          if(doctv_from == "" || isNaN(doctv_from)){
              // when from empty
              jQuery(this).find(".doctv_from").css("border"," 1px solid red");
              flag1++;
              //return false;
          } 
          
        }

        //  validation that there are no overlaps with the periods
        if (!fromToArr.hasOwnProperty(doctv_currency)) {
          fromToArr[doctv_currency] = {};  
        }
        var countObj = Object.keys(fromToArr[doctv_currency]).length;
        fromToArr[doctv_currency][countObj] = {};
        fromToArr[doctv_currency][countObj]["from"] = doctv_from;
        fromToArr[doctv_currency][countObj]["to"] = doctv_to;
        fromToArr[doctv_currency][countObj]["currency"] = doctv_currency;
        if(flag1 == 0 && Object.keys(fromToArr[doctv_currency]).length > 1){
          for(var j=0; j<Object.keys(fromToArr[doctv_currency]).length-1; j++){
             if(((doctv_from >= fromToArr[doctv_currency][j]["from"] && doctv_from <= fromToArr[doctv_currency][j]["to"]) || (doctv_to >= fromToArr[doctv_currency][j]["from"] && doctv_to <= fromToArr[doctv_currency][j]["to"])) && doctv_currency == fromToArr[doctv_currency][j]["currency"]){
              console.log("forrrr");
              jQuery(this).find(".doctv_from").css("border","1px solid red");
              jQuery(this).find(".doctv_to").css("border","1px solid red");
              flag1++;
              overlaps++;
            }
            // check if there is gap between previous to and next from
            if(((fromToArr[doctv_currency][j]["to"]+1) != fromToArr[doctv_currency][j+1]["from"]) && doctv_currency == fromToArr[doctv_currency][j]["currency"]){
              jQuery(this).find(".doctv_from").css("border","1px solid red");
              jQuery(this).find(".doctv_to").css("border","1px solid red");
              flag1++;
              hasGap++;  
            } 
          }
        }

        i++;

        // check if from is bigger than to
        if(doctv_from > doctv_to){
          jQuery(this).find(".doctv_from").css("border","1px solid red");
          jQuery(this).find(".doctv_to").css("border","1px solid red");  
          fromBigger++;
        }

        });
      }

      // 
      if(jQuery("select[id^=payment_][id$=_splitit_paymentmethod_first_payment]:first").val() == "percentage"){
        var percentageOfOrder = jQuery("[id^=payment_][id$=_splitit_paymentmethod_percentage_of_order]:first").val();
        if(percentageOfOrder > 50 ){
          percentageFlag++; 
          flag1++; 
        }
        
      }
      if(flag1 == 0){
        if(jQuery('[id^=payment_][id$=_splitit_paymentmethod_select_installment_setup]:first').val() == 'depending_on_cart_total'){
          createJsonOfDependingOnCartTotal(); 
        }
        eval(configForm.submit());
      }else{
        if(fromBigger){
          alert("From amount should be lesser than To.");
        }
        else if(overlaps){
          alert("From and To amount should not Overlap");
        }
        else if(percentageFlag){
          jQuery("#payment_pis_cc_percentage_of_order").css("border","1px solid red");
          alert("Please enter Percentage of order % <= 50");
        }else if(hasGap){
          alert("There should not be Gap between To and From amounts.");
        }else{
          alert('Please fill the required fields in Splitit section "Depending on cart total"');  
        }
        
         return false;
      }
       

    });

  

  jQuery(document).on('change', '[id^=payment_][id$=_splitit_paymentmethod_select_installment_setup]:first', function(){
    if(jQuery(this).val() == 'fixed'){
      jQuery('#tiers_table.splitit').closest('td').addClass('not-allowed-td');
      jQuery('[id^=payment_][id$=_splitit_paymentmethod_fixed_installment]:first').removeAttr('disabled');
    }else{
      jQuery('#tiers_table.splitit').closest('td').removeClass('not-allowed-td');
      jQuery('[id^=payment_][id$=_splitit_paymentmethod_fixed_installment]:first').attr('disabled', 'disabled');
    }
  });

  jQuery(document).on("click","[id^=payment_][id$=_splitit_paymentmethod_check_setting]:first", function(){
    checkSetting();  
  });
}

function getCurrency(){
  
  
  jQuery.ajax({
    url: baseUrl + "splititpaymentmethod/getcurrency/getcurrency", 
    showLoader: true,
    success: function(result){
      jQuery('#tiers_table.splitit').find("input.doctv_currency").val(result.currencyCode);
      jQuery('#tiers_table.splitit').find("span.base-currency-symbol").html(result.currencySymbol);
      baseCurrencyCode = result.currencyCode;
      baseCurrencySymbol = result.currencySymbol;
      currencyCodeSymbol = result.currencyCodeSymbol;

      // console.log(jQuery("#payment_us_splitit_paymentmethod_depending_on_cart_total").length);
      // show depanding on cart table
      var tableHtml = getTableHtml();
//      jQuery("#payment_us_splitit_paymentmethod_depending_on_cart_total").replaceWith(tableHtml);
      jQuery('[name="groups[splitit_paymentmethod][fields][depending_on_cart_total][value]"]').replaceWith(tableHtml);
      // disable or enable Fixed and Depanding on cart total
      if(jQuery("[id^=payment_][id$=_splitit_paymentmethod_select_installment_setup]:first").val() == 'fixed'){
        jQuery('#tiers_table.splitit').closest('td').addClass('not-allowed-td');
        jQuery('[id^=payment_][id$=_splitit_paymentmethod_fixed_installment]:first').removeAttr('disabled');
      }else{
        jQuery('#tiers_table.splitit').closest('td').removeClass('not-allowed-td');
        jQuery('[id^=payment_][id$=_splitit_paymentmethod_fixed_installment]:first').attr('disabled', 'disabled');
      }
    }
  });
}

function getTableHtml(){
  var html = '<table class="data border splitit" id="tiers_table" cellspacing="0" border="1">'
        +'<div class="tiers_table_overlay"></div>'
        +'<thead>'
        +'<tr class="headings">'
        +'<th style="min-width: 100px;">Cart total</th>'
        +'<th style="min-width: 160px;">#Installments</th>'
        +'<th >Currency</th>'
        +'<th class="last">Action</th>'
        +'</tr>'
        +'</thead>'
        +'<tbody id="tier_price_container">';
    html += getTableInnerContent();    
    html += '</tbody>'
        +'<tfoot>'
        +'<tr>'
        +'<td style="display:none"></td>'
        +'<td colspan="4" align="right"><button id="" title="Add Tier" type="button" class="scalable add add-tier" onclick="addRow();" style=""><span><span><span>Add Tier</span></span></span></button></td>'
        +'</tr>'
        +'</tfoot>'
        +'</table>';
    return html;
}

function getTableInnerContent(){
  var jsonValue = jQuery("[id^=payment_][id$=_splitit_paymentmethod_depanding_on_cart_total_values]:first").val();
  if(jsonValue == "" || jsonValue == undefined){
    return getRowHtml();
  }else{
    return getRowHtmlFromJson();
  }
}

function getRowHtml(){
  var rowHtml = '<tr>'
         +'<td style="padding: 8px;">'
          +'From<br><span class="base-currency-symbol">'+getCurrencyCode("")+'</span><input type="text" class="doctv_from" name="doctv_from" /><br>To<br><span class="base-currency-symbol">'+getCurrencyCode("")+'</span><input type="text" class="doctv_to" name="doctv_to" />'
         +'</td>'
         +'<td style="padding: 8px;">'
          +'<select id="doctv_installments" name="doctv_installments" class=" select multiselect doctv_installments" size="10" multiple="multiple">';
    for(var i=2; i<=24; i++){
        rowHtml += '<option value="'+i+'">'+i+' Installments</option>';
    }
    rowHtml+='</select>'
         +'</td>'
         +'<td style="padding: 8px; text-align: center;">'
           +getCurrencyDropdown("")
         +'</td>'
         +'<td style="padding: 8px; text-align: center;">'
          +'<button title="Delete Tier" type="button" class="scalable delete icon-btn delete-product-option" id="" onclick="deleteRow(this);"><span><span><span>Delete</span></span></span></button>'
         +'</td>'
      +'</tr>';

    return rowHtml;  
}

function getRowHtmlFromJson(){
  var doctv = JSON.parse(jQuery("[id^=payment_][id$=_splitit_paymentmethod_depanding_on_cart_total_values]:first").val());
  var rowHtml = "";
  jQuery.each( doctv, function( index, value ){
      rowHtml += '<tr>';
        rowHtml += '<td style="padding: 8px;"> From<br><span class="base-currency-symbol">'+getCurrencyCode(value.doctv.currency)+'</span><input type="text" class="doctv_from" name="doctv_from" value="'+value.doctv.from+'" /><br>To<br><span class="base-currency-symbol">'+getCurrencyCode(value.doctv.currency)+'</span><input type="text" class="doctv_to" name="doctv_to" value="'+value.doctv.to+'" /> </td>';
        rowHtml += '<td style="padding: 8px;"> <select id="" name="doctv_installments" class=" select multiselect doctv_installments" size="10" multiple="multiple">';
        var installments = value.doctv.installments.split(',');
        var i = 2;
        var selected = "";
        for(i=2; i<=24; i++){
          if(jQuery.inArray(i.toString(), installments) != -1){
            selected = 'selected="selected"';
          }
          rowHtml += '<option value="'+i+'" '+selected+'>'+i+' Installments</option>';  
            selected = "";

        }
        rowHtml += '</select></td>';
        rowHtml += '<td style="padding: 8px; text-align: center;">'+getCurrencyDropdown(value.doctv.currency)+'</td>'; 
        rowHtml += '<td style="padding: 8px; text-align: center;">'
                +'<button title="Delete Tier" type="button" class="scalable delete icon-btn delete-product-option" id="" onclick="deleteRow(this);"><span><span><span>Delete</span></span></span></button>'
              +'</td>'
              +'</tr>';

  });
  return rowHtml;
}

function addRow(){
  var rowHtml = getRowHtml();
  jQuery("#tiers_table.splitit tbody#tier_price_container").append(rowHtml);
}
function deleteRow(curObj){
    var count = jQuery(curObj).closest("tbody").find("tr").length;
    if(count > 1){
        jQuery(curObj).closest("tr").remove();    
    }
    
}
function getCurrencyDropdown(selectedCurrency){
    var currencyDropdown = '<select id="" class="doctv_currency" name="doctv_currency" class=" select">';
    var selected = "";       
    jQuery.each(currencyCodeSymbol, function(i, val) {
      selected = ""
        if(selectedCurrency == i){
            selected = 'selected="selected"';
        }
        currencyDropdown += '<option value="'+i+'" '+selected+'>'+i+'</option>';    
    });
    return currencyDropdown += '</select>';

}

function getCurrencyCode(selectedCurrency){
    var currencyCode = "";

    if(selectedCurrency == ""){
        var flag = 0;
        jQuery.each(currencyCodeSymbol, function(i, val) {
            if(flag == 0){
              currencyCode = val;
            }
            flag++;
        });
        return currencyCode;

    }
    if (currencyCodeSymbol.hasOwnProperty(selectedCurrency)){
        return currencyCodeSymbol[selectedCurrency];
    } 
}

function createJsonOfDependingOnCartTotal(){
     var i = 0;
     var object = {};

     jQuery("#tiers_table.splitit tbody").find('tr').each(function() {
        var $this = jQuery(this);
        var installments = [];
        object[i] = {};
        object[i]["doctv"] = {};
        object[i]["doctv"]["from"] = $this.find("td:first-child").find("input.doctv_from").val();
        object[i]["doctv"]["to"] = $this.find("td:first-child").find("input.doctv_to").val(); 
        $this.find("td:nth-child(2)").find(".doctv_installments :selected").each(function(i, selected){ 
           installments.push(jQuery(selected).val());
        });
        object[i]["doctv"]["installments"] = installments.toString();
        object[i]["doctv"]["currency"] = $this.find("td:nth-child(3)").find(".doctv_currency").val();
       
        i++;      
    });
    object = JSON.stringify(object);
    jQuery("[id^=payment_][id$=_splitit_paymentmethod_depanding_on_cart_total_values]:first").val(object);

}

function checkSetting(){

  jQuery.ajax({
    url: baseUrl + "splititpaymentmethod/checksetting/checksetting", 
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

function splitit_fee_types(){
    jQuery(document).on('change','[id^=payment_][id$=_splitit_paymentmethod_splitit_fee_types]:first',function(){
        jQuery('[id^=payment_][id$=_splitit_paymentmethod_splitit_fees]:first').trigger('change');
    });
    jQuery(document).on('change','[id^=payment_][id$=_splitit_paymentredirect_splitit_fee_types]:first',function(){
        jQuery('[id^=payment_][id$=_splitit_paymentredirect_splitit_fees]:first').trigger('change');
    });
    jQuery(document).on('change','[id^=payment_][id$=_splitit_paymentmethod_splitit_fees]:first',function(){
        if(jQuery(this).val()>50){
        if(jQuery('[id^=payment_][id$=_splitit_paymentmethod_splitit_fee_types]:first').val()==1){
            jQuery(this).val(50);
        }
        }
    });
    jQuery(document).on('change','[id^=payment_][id$=_splitit_paymentredirect_splitit_fees]:first',function(){
        if(jQuery(this).val()>50){
        if(jQuery('[id^=payment_][id$=_splitit_paymentredirect_splitit_fee_types]:first').val()==1){
            jQuery(this).val(50);
        }
        }
    });
}

function splitit_fee_table(){
    var table=jQuery('[id^=row_payment_][id$=_splitit_paymentmethod_splitit_fee_table]:first');
    console.log('row_payment_us_splitit_paymentmethod_splitit_fee_table loaded');
    while(table.find('tbody tr').length!=23){
        table.find('button.action-add').click();
    }
    table.find('tfoot').remove();
    table.find('th').last().remove();
    var i=2;
    table.find('tbody tr').each(function(){
        var tr=jQuery(this);
        tr.find('td input[name*="noi"]').attr('readonly',true).val(i++);
        if(tr.find('td input[name*="fixed"]').val()==''||parseFloat(tr.find('td input[name*="fixed"]').val())<=0){
            tr.find('td input[name*="fixed"]').val('0.00');
        }
        if(tr.find('td input[name*="percent"]').val()==''||parseFloat(tr.find('td input[name*="percent"]').val())<=0){
            tr.find('td input[name*="percent"]').val('0.00');
        }
        tr.find('td input[name*="percent"]').on('change',function(){
            if(parseFloat(jQuery(this).val())>30){
                alert("Percent value cannot be greater than 30");
                jQuery(this).val('30.00');
            }
        });
        tr.find('td.col-actions').remove();
    });
}