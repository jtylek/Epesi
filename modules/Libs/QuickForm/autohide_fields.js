var Libs_QuickForm__hide_groups = {};
Libs_QuickForm__autohide = function(e) {
	var el = jq(e.target);
	// do not handle autohide when element is autohidden
	if (el.hasClass('autohide')) return;
	var hide_groups = Libs_QuickForm__hide_groups[el.attr('id')];
	if(typeof hide_groups == "undefined") return;
	var reverse_mode = {
		'hide' : 'show',
		'show' : 'hide'
	};
	
	var multi = ((el.prop('tagName').toLowerCase()=='select') && (el.attr('name').match(/\_\_display$/) || el.attr('id').match(/\_\_to$/)));
	var set_fields = {};
	jq.each(hide_groups, function(i, group) {
		var autohide_values = group.values;
		var val;
		if(multi) {
			val = [];
			jq('option',el).each(function() {
			    val.push(jq(this).val());
			});
		} else if (el.attr('type') == 'checkbox') {
			val = el.is(':checked') ? '1' : '0';
		} else if (el.attr('type') == 'hidden' && el.val().indexOf('__SEP__')!== -1) {
			val = el.val().split('__SEP__');
			multi = true;
		} else {
			val = el.val();
		}

		var found = false;
		if(multi) {
			jq.each(val,function(idx,val2) {
				found = found || autohide_values.indexOf(val2) > -1;
			});
		} else {
			found = autohide_values.indexOf(val) > -1;
		}

		if (found) {
			var confirmed = true;
			if (typeof group.confirm !== 'undefined')
				confirmed = confirm(group.confirm);

			if (confirmed) {
				jq(group.fields).closest('tr')[group.mode]();
				jq(group.fields)[group.mode]().removeClass('auto' + reverse_mode[group.mode]).addClass('auto' + group.mode); // hide/show element to trigger nested autohide
			}
		} else {
			if (group.autoReverse) {
				//apply reverse mode only to fields not specifically set
				not_set_fields = jq(group.fields).not(set_fields[group.mode]).get();
				jq(not_set_fields).closest('tr')[reverse_mode[group.mode]]();
				jq(not_set_fields)[reverse_mode[group.mode]]().removeClass('auto' + group.mode).addClass('auto' + reverse_mode[group.mode]); // hide/show element to trigger nested autohide
			}
		}
		
		set_fields[group.mode] = group.fields;
	});
}