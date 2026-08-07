(function($){
	function openModal($modal){
		$modal.attr('aria-hidden','false');
		$('body').addClass('crh-modal-open');
	}
	function closeModal($modal){
		$modal.attr('aria-hidden','true');
		$('body').removeClass('crh-modal-open');
	}
	function renderRow(name, qty){
		name = name || '';
		qty = qty || 1;
		return '<div class="crh-row">\
		  <input class="crh-name" type="text" placeholder="Area Name" value="'+ _.escape(name) +'" list="crh-areas-datalist"/>\
		  <input class="crh-qty" type="number" min="1" value="'+ qty +'"/>\
		  <button type="button" class="button crh-del">×</button>\
		</div>';
	}

	// Datalist for product modal
	$(document).on('mouseenter focus','#crh-areas-modal', function(){
		if ($('#crh-areas-datalist').length) return;
		var names = (window.CRH_AREAS_DATA && CRH_AREAS_DATA.cartAreaNames) || [];
		var html = '<datalist id="crh-areas-datalist">'+ names.map(function(n){return '<option value="'+ _.escape(n) +'"></option>';}).join('') +'</datalist>';
		$('body').append(html);
	});

	// Capture add-to-cart click on product page
	$(document).on('click', 'form.cart button.single_add_to_cart_button', function(ev){
		var $btn = $(this);
		var $form = $btn.closest('form.cart');
		var $modal = $('#crh-areas-modal');
		if (!$modal.length) return; // fallback: no modal present

		var qtyField = $form.find('input.qty');
		var total = parseInt(qtyField.val(), 10) || 1;
		$('#crh-total-qty').text(total);

		var $rows = $('#crh-area-rows').empty();
		$rows.append(renderRow('', total));
		$('.crh-error').hide().text('');

		openModal($modal);
		ev.preventDefault();
	});

	$(document).on('click', '.crh-modal__close', function(){ closeModal($('#crh-areas-modal')); });
	$(document).on('click', '.crh-no-areas', function(){
		$('#crh_areas_json').val('[]');
		closeModal($('#crh-areas-modal'));
		$('form.cart').trigger('submit');
	});
	$(document).on('click', '.crh-add-row', function(){
		$('#crh-area-rows').append(renderRow('', 1));
	});
	$(document).on('click', '.crh-del', function(){
		$(this).closest('.crh-row').remove();
	});

	function collect(){
		var rows = [];
		$('#crh-area-rows .crh-row').each(function(){
			var name = $.trim($(this).find('.crh-name').val());
			var qty  = parseInt($(this).find('.crh-qty').val(), 10) || 0;
			if (name && qty>0){ rows.push({name:name, qty:qty}); }
		});
		return rows;
	}
	function sum(rows){
		var s = 0; rows.forEach(function(r){ s += r.qty; }); return s;
	}

	$(document).on('click', '.crh-allocate', function(){
		var total = parseInt($('#crh-total-qty').text(), 10) || 0;
		var rows = collect();
		if (!rows.length){
			$('.crh-error').text('Please add at least one area or choose No Areas Required.').show();
			return;
		}
		if (sum(rows) !== total){
			$('.crh-error').text('Total allocated quantity does not match product quantity.').show();
			return;
		}
		$('#crh_areas_json').val(JSON.stringify(rows));
		closeModal($('#crh-areas-modal'));
		$('form.cart').trigger('submit');
	});

})(jQuery);
