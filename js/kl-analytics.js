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

/*
function klala_toggle(id) {
	if (document.getElementById(id).style.display != "block") {
		document.getElementById(id).style.display = "block";
		document.getElementById(id).className = "open";		
	} else {
		document.getElementById(id).style.display = "none";
		document.getElementById(id).className = "closed";				
	}
}
* */

// hacky accordion
function klala_accordion_toggle(link, target) {	
	var link = jQuery('#'+link);
	if (link.hasClass('open')) {
		link.removeClass('open');
		link.addClass('closed');
		jQuery('#'+target).hide();
	} else {
		link.removeClass('closed');
		link.addClass('open');
		jQuery('#'+target).show();
	}
}

// thanks https://stackoverflow.com/questions/901115/how-can-i-get-query-string-values-in-javascript
function klala_getParameterByName(name, url) {
    if (!url) url = window.location.href;
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}

jQuery(document).ready(function() {
	klala_hideTabs();
	var tab = klala_getParameterByName('tab');
	if (tab) {
		klala_showTab(tab);
		jQuery('#nav-link-'+tab).addClass('active');
	} else {
		klala_showTab('overview');
		jQuery('#nav-link-overview').addClass('active');
	}
	
	// hacky active tab indicator
	jQuery('.nav-link').click(function() {
		jQuery('.nav-link').removeClass('active');
		jQuery(this).addClass('active');
	});
	
	
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

