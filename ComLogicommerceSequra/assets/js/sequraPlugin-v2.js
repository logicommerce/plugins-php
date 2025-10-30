var SEQURA_PAYMENT_MODULE = 'com.logicommerce.sequra';
var selected = $('input[name="paymentSystem"]:checked');

LC.resources.addPluginListener('initializePaymentsBefore', function(form, oneStepCheckout) {
	if (typeof Sequra == "undefined" || !window.Sequra) {
		return false;
	}
	if (oneStepCheckout) {
		Sequra.onLoad(function() { Sequra.refreshComponents(); });
	}
}, true);

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
	var checkoutForm = data;
	if (oneStepCheckout == true) {
		buttonStep = '#basketEndOrder';
		checkoutForm = data.el.$form;
	} else {
		buttonStep = '#paymentAndShippingBasketContinue';
	}
	$(buttonStep).prop('disabled', true);
	var selected = checkoutForm.find('.basketSelectorPaymentInput:checked');
	var paymentSystemSelected = selected.val() || 0;
	var paymentSystemId = JSON.parse(paymentSystemSelected).id;
	if (paymentSystemId == 0) {
		return false;
	}
	if (selected.attr("data-plugin-module") == SEQURA_PAYMENT_MODULE) {
		ev.preventDefault();
		checkoutForm.preventSubmit = true;
		$(buttonStep).prop('disabled', true);

		var additonalData = { osc: oneStepCheckout };
		if (oneStepCheckout == true) {
			additonalData = { updateBasketRows: data.updateBasketRows, action: data.submitButton.val(), osc: oneStepCheckout };
		}
		$.post(LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP,
		{
			data: JSON.stringify($.extend(data.dataForm, additonalData))
		},
		(response) => {
			var paymentCallback = function(response) {
				if (response.length > 0 && !response.includes("deniedOrder")) {
					$(".basketPaymentIframe" + $('.sequra-promotion-widget').attr('data-product')).html(response);
					$(".basketPaymentIframe" + $('.sequra-promotion-widget').attr('data-product')).css("display", "block");
					setTimeout(function() {
						window.SequraFormInstance.show();
						window.SequraFormInstance.setCloseCallback(function(e){window.location.reload();});
					}, 1000);
				}
				else {
					window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
				}
			};
			$.post(LC.global.routePaths.CHECKOUT_END_ORDER, {}, paymentCallback, 'html');
		}).fail(function() {
			$(buttonStep).prop('disabled', false);
		});
	}
	return false;
}, true);