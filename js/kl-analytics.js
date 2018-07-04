function klala_hideTabs() {	
	jQuery('.klala-tab').hide();
}

function klala_showTab(id) {
	jQuery('#'+id).show();
}

function klala_switchTab(id) {
	klala_hideTabs();
	klala_showTab(id);
}

function klala_toggle(id) {
	if (document.getElementById(id).style.display != "block") {
		document.getElementById(id).style.display = "block";
		document.getElementById(id).className = "open";		
	} else {
		document.getElementById(id).style.display = "none";
		document.getElementById(id).className = "closed";				
	}
}


jQuery(document).ready(function() {
	klala_hideTabs();
	klala_showTab('overview');
	
	// try add datatables
	try {
		// lookup datetime column index for sort
		ths = jQuery('table#klala_data_table th');
		var index = 1; // default
		for (var c = 0; c < ths.length; c++) {
			if (ths[c].innerHTML.indexOf('datetime') > -1) {
				index = c;
				break;
			}
		}			
		jQuery('#klala_data_table').DataTable( {
			"order": [[ index, "desc" ]]
		} );
	} catch (error) {
		console.log("Error applying datatables");
	} 
	
	
});

