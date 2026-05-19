/**
 * @name Bizuno ERP
 * @copyright 2008-2018, PhreeSoft, www.PhreeSoft.com
 * @author Dave Premo, PhreeSoft
 * @version 1.0 Last Update: 2020-11-23
 * @filesource /EXTENSION_PATH/ifWalmart/ifWalmart.js
 */

function walmartContact() {
	jqBiz('#general_contact_id').combogrid({
		mode:      'remote',
		url:       bizunoAjax+'&bizRt=contacts/main/managerRows&type=c',
		panelWidth:450,
		delay:     900,
		idField:   'id',
		textField: 'primary_name',
		formatter: function(row){
			var opts = jqBiz(this).combobox('options');
			return row[opts.textField];
		},
		columns:[[
			{field:'id', hidden:true},
			{field:'short_name',  title:bizLangJS('CONTACT_ID'),width:100},
			{field:'primary_name',title:bizLangJS('NAME'),      width:200},
			{field:'city',        title:bizLangJS('CITY'),      width:100},
			{field:'state',       title:bizLangJS('STATE'),     width: 50},
		]]
	});
}

/**
 * This function generates a form to upload the walmart payment file
 */
function reconcileWalmart() {
	jqBiz.ajax({
        url: bizunoAjax+'&bizRt=proIF/admin/paymentFileForm&modID=ifWalmart',
		success: function (data) { processJson(data); } // should pull up the upload form
	});
}

function processWalmart(json) {
	if (!json.payments.length) { alert('No file length'); return; }
	var msg   = '';
	var oID   = '';
	var total = 0;
	var fees  = 0;
	jqBiz('#winNewPmt').window('destroy');
	allText = window.atob(json.payments);
	var allTextLines = allText.split(/\r\n|\n/);
	var headers = allTextLines[0].split('\t');
	var lines = [];
//	for (var i=1; i<allTextLines.length; i++) { // old way
	for (var i=2; i<allTextLines.length; i++) { // new way, the second line is just dates
		var data = allTextLines[i].split('\t');
		if (data.length == headers.length) {
			var tarr = [];
			for (var j=0; j<headers.length; j++) tarr[headers[j]] = data[j];
			lines.push(tarr);
		}
	}
	var dgData = jqBiz('#dgJournalItem').edatagrid('getRows');
	var rowsChecked = [];
	for (var i=0; i<lines.length; i++) {
		oID       = lines[i]['order_id'];
		next_oID  = i < (lines.length-1) ? lines[(i+1)]['order_id'] : '';
/* the old way (before 2015-04-06)
		total += isNaN(parseFloat(lines[i]['item_price_credit']))    ? 0 : parseFloat(lines[i]['item_price_credit']);
		total += isNaN(parseFloat(lines[i]['shipping_price_credit']))? 0 : parseFloat(lines[i]['shipping_price_credit']);
		fees  += isNaN(parseFloat(lines[i]['order_related_fees']))   ? 0 : parseFloat(lines[i]['order_related_fees']);
*/
// The new way
		if (lines[i]['price_type'] == 'Principal' || lines[i]['price_type'] == 'Shipping') {
			total += isNaN(parseFloat(lines[i]['price_amount'])) ? 0 : parseFloat(lines[i]['price_amount']);
		}
		if (lines[i]['item_related_fee_type'] == 'Commission' || lines[i]['item_related_fee_type'] == 'ShippingHB' || lines[i]['item_related_fee_type'] == 'RefundCommission') {
			fees  += isNaN(parseFloat(lines[i]['item_related_fee_amount'])) ? 0 : parseFloat(lines[i]['item_related_fee_amount']);
		}
// End new way
		if (oID && next_oID == oID) {
//			alert('i = '+i+' oID = '+oID+' and next oID = '+lines[(i+1)]['order_id']); //continue; // another line, continue to add
//			continue;
		} else {
			var orderTotal= Math.round(total * 100) / 100;
			var rowTotal= total + fees;
			var found   = false;
			var amount  = 0;
			for (var j=0; j<dgData.length; j++) {
				var desc = dgData[j]['description'];
				if (desc.indexOf(oID) >= 0 && oID != '') { // if the description contains oID, it's a hit
					amount = cleanCurrency(dgData[j]['amount']);
					if (orderTotal == amount) {
						dgData[j]['discount'] = -fees;
						dgData[j]['total']    = cleanCurrency(dgData[j]['total']) + fees;
						rowsChecked.push(j);
						found = true;
						break;
					}
				}
			}
			if (!found) {
				var transType = lines[i]['transaction_type'];
				if (transType == 'Order') {
					msg += 'Balance mismatch. Order ID: '+oID+', Bizuno Total: '+amount+' versus Walmart Total: '+orderTotal+".\n";
				} else if (transType != '') {
					// add some other amounts
					rowTotal += isNaN(parseFloat(lines[i]['other_amount'])) ? 0 : parseFloat(lines[i]['other_amount']);
					msg += 'Line Not Found: Type: '+transType+' for '+rowTotal+".\n";
				}
			}
			total = 0;
			fees  = 0;
		}
	}
	jqBiz('#dgJournalItem').datagrid( { data: dgData } ); // update datagrid
	for (var i=0; i<rowsChecked.length; i++) jqBiz('#dgJournalItem').edatagrid('checkRow', rowsChecked[i]); // check the affected rows
	jqBiz('body').removeClass('loading');
	if (msg) alert(msg);
}
