/*
 * @name Bizuno ERP - Amazon Interface Extension - JavaScript client script
 *
 * NOTICE OF LICENSE
 * This software may be used only for one installation of Bizuno when
 * purchased through the PhreeSoft.com website store. This software may
 * not be re-sold or re-distributed without written consent of PhreeSoft.
 * Please contact us for further information or clarification of you have
 * any questions.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to automatically upgrade to
 * a newer version in the future. If you wish to customize this module, you
 * do so at your own risk, PhreeSoft will not support this extension if it
 * has been modified from its original content.
 *
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    PhreeSoft Proprietary
 * @version    7.x Last Update: 2025-04-22
 * @filesource /EXTENSION_PATH/ifAmazon/ifAmazon.js
 */

/**
 * This function generates a form to upload the amazon payment file
 */
function reconcileAmazon() {
    jqBiz.ajax({
        url: bizunoAjax+'&bizRt=api/admin/paymentFileForm&modID=ifAmazon',
        success: function (data) { processJson(data); } // should pull up the upload form
    });
}

function processAmazon(json) {
    if (!json.payments.length) { alert('No file length'); return; }
    var message= '';
    var oID    = '';
    var total  = 0;
    var fees   = 0;
    var taxCrd = 0;
    var debits = ['Commission', 'ShippingHB', 'RefundCommission', 'SalesTaxServiceFee'];
    var credits= ['Principal', 'Tax', 'Shipping', 'ShippingTax'];
    var mfTax  = ['MarketplaceFacilitatorTax-Shipping', 'MarketplaceFacilitatorTax-Principal'];
    jqBiz('#winNewPmt').window('destroy');
    allText = window.atob(json.payments);
    var allTextLines = allText.split(/\r\n|\n/);
    var headers = allTextLines[0].split('\t');
    var lines = [];
    for (var i=2; i<allTextLines.length; i++) {
        var data = allTextLines[i].split('\t');
        if (data.length == headers.length) {
            var tarr = [];
            for (var j=0; j<headers.length; j++) { tarr[headers[j]] = data[j]; }
            lines.push(tarr);
        }
    }
    var dgData = jqBiz('#dgJournalItem').edatagrid('getRows');
    var rowsChecked = [];
    for (var i=0; i<lines.length; i++) {
        oID        = lines[i]['order_id'];
        next_oID   = i < (lines.length-1) ? lines[(i+1)]['order_id'] : '';
        if (jqBiz.inArray(lines[i]['amount_description'], credits) > -1) {
            total += isNaN(parseFloat(lines[i]['amount'])) ? 0 : parseFloat(lines[i]['amount']);
        }
        if (jqBiz.inArray(lines[i]['amount_description'], mfTax) > -1) {
            taxCrd-= isNaN(parseFloat(lines[i]['amount'])) ? 0 : parseFloat(lines[i]['amount']);
            fees  += isNaN(parseFloat(lines[i]['amount'])) ? 0 : parseFloat(lines[i]['amount']);
        }
        if (jqBiz.inArray(lines[i]['amount_description'], debits) > -1) {
            fees  += isNaN(parseFloat(lines[i]['amount'])) ? 0 : parseFloat(lines[i]['amount']);
        }
        if (oID && next_oID == oID) { // no finished yet with this order
//            alert('i = '+i+' oID = '+oID+' and next oID = '+lines[(i+1)]['order_id']); //continue; // another line, continue to add
        } else {
            var orderTotal= Math.round(total * 100) / 100;
            var rowTotal= total + fees;
            var found   = false;
            var amount  = 0;
            for (var j=0; j<dgData.length; j++) {
                var desc = dgData[j]['description'];
                if (desc.indexOf(oID) >= 0 && oID != '') { // if the description contains oID, it's a hit
                    amount = (dgData[j]['amount']);
                    if (orderTotal == amount) {
                        dgData[j]['discount'] = roundCurrency(-fees);
                        dgData[j]['total']    = roundCurrency((dgData[j]['total']) + fees);
                        rowsChecked.push(j);
                        found = true;
                        break;
                    }
                }
            }
            if (!found) {
                var transType = lines[i]['transaction_type'];
                if (transType == 'Order') {
                    message += 'Balance mismatch. Order ID: '+oID+', Bizuno Total: '+amount+' versus Amazon Total: '+roundCurrency(orderTotal)+" with fees: "+roundCurrency(-fees)+".<br />";
                } else if (transType != '') {
                    // add some other amounts
                    rowTotal += isNaN(parseFloat(lines[i]['amount'])) ? 0 : parseFloat(lines[i]['amount']);
                    message += 'Line Not Found: Type: '+transType+' for '+rowTotal+".<br />";
                }
            }
            total = 0;
            fees  = 0;
        }
    }
    if (taxCrd) message += '<br />Create journal entry: Sales Tax Marketplace Facilitator for '+formatCurrency(taxCrd)+', debit: '+AmazonGlTax+', credit: '+AmazonGlAr+'<br /><br />';
    jqBiz('#dgJournalItem').datagrid( { data: dgData } ); // update datagrid
    for (var i=0; i<rowsChecked.length; i++) jqBiz('#dgJournalItem').edatagrid('checkRow', rowsChecked[i]); // check the affected rows
    jqBiz('body').removeClass('loading');
    processJson( { action:'window', id:'amazonRecon', title:'Amazon Reconciliation Results', html:message } );
}
