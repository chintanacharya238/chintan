(function($){
	function rowTemplate(name, qty){
		name = name || '';
		qty = qty || 1;
		return '<div class="crh-panel-row">\
		  <input class="crh-area-name" type="text" placeholder="Area Name" value="'+ _.escape(name) +'" list="crh-cart-datalist"/>\
		  <input class="crh-area-qty" type="number" min="1" value="'+ qty +'"/>\
		  <button type="button" class="button crh-del-row" aria-label="Remove">×</button>\
		</div>';
	}

	// Build a single datalist shared across cart
	$(function(){
		if ($('#crh-cart-datalist').length) return;
		var names = (window.CRH_AREAS_DATA && CRH_AREAS_DATA.cartAreaNames) || [];
		var html = '<datalist id="crh-cart-datalist">'+ names.map(function(n){return '<option value="'+ _.escape(n) +'"></option>';}).join('') +'</datalist>';
		$('body').append(html);
	});

	$(document).on('click', '.crh-acc__toggle', function(){
		var $panel = $(this).closest('.crh-acc').find('.crh-acc__panel');
		var expanded = $(this).attr('aria-expanded') === 'true';
		$(this).attr('aria-expanded', expanded ? 'false' : 'true');
		$panel.slideToggle(150);
	});

	$(document).on('click', '.crh-acc .crh-add-row', function(){
		var $acc = $(this).closest('.crh-acc');
		$acc.find('.crh-panel-rows').append(rowTemplate('', 1));
	});
	$(document).on('click', '.crh-acc .crh-del-row', function(){
		$(this).closest('.crh-panel-row').remove();
	});

	function collectRows($acc){
		var rows = [];
		$acc.find('.crh-panel-row').each(function(){
			var name = $.trim($(this).find('.crh-area-name').val());
			var qty  = parseInt($(this).find('.crh-area-qty').val(), 10) || 0;
			if (name && qty>0){ rows.push({name:name, qty:qty}); }
		});
		return rows;
	}

	function sum(rows){ var s=0; rows.forEach(function(r){ s+=r.qty; }); return s; }

	$(document).on('click', '.crh-acc .crh-save', function(){
		var $acc = $(this).closest('.crh-acc');
		var key  = $acc.data('cart-key');
		var rows = collectRows($acc);
		var totalQty = parseInt($acc.closest('tr').find('.crh-managed-qty small').text().replace(/[()]/g,''), 10) || 0;

		var $msg = $acc.find('.crh-panel-msg').removeClass('crh-error').text('');
		if (sum(rows) !== totalQty){
			$msg.addClass('crh-error').text('Allocated quantity must equal item quantity.');
			return;
		}
		$.ajax({
			type: 'POST',
			url: CRH_AREAS_DATA.ajax,
			data: {
				action: 'crh_save_areas',
				nonce: CRH_AREAS_DATA.nonce,
				cart_item_key: key,
				areas: JSON.stringify(rows)
			},
			success: function(resp){
				if (resp && resp.success){
					$msg.text('Saved.');
					// Optionally refresh totals via fragment refresh
					$(document.body).trigger('wc_update_cart');
				} else {
					$msg.addClass('crh-error').text((resp && resp.data && resp.data.msg) || 'Error saving.');
				}
			},
			error: function(){ $msg.addClass('crh-error').text('Error saving.'); }
		});
	});
})(jQuery);
