var SPRINQUE_PAYMENT_MODULE = 'com.logicommerce.sprinque';
var selected = $('input[name="paymentSystem"]:checked');
var lcSprinque = {};
var lcBuyerId = "";
var CHECKOUT_URL = LC.global.routePaths.CHECKOUT;

LC.resources.addPluginListener('initializePaymentsCallback', function(form, oneStepCheckout) {
	lcSprinque = new LcSprinque();
	lcSprinque.SPRINQUE_PAYMENT_MODULE = 'com.logicommerce.sprinque';
	var selected = form.find('.basketSelectorPaymentInput:checked');
	var paymentSystemSelected = selected.val() || 0;
	var paymentSystemId = JSON.parse(paymentSystemSelected).id;	
	if (paymentSystemId == 0) {
		return false;
	}
	try {
		var sprinqueDataId = "#sprinqueBuyerData";
		var sprinqueDataConfig = $(sprinqueDataId).attr('data-config');
		sprinqueDataConfig = JSON.parse(sprinqueDataConfig ? sprinqueDataConfig : "{}");
	} catch (error) {
		console.log(error);
	}
	lcSprinque.setDataConfig(sprinqueDataConfig);
	lcSprinque.checkShowSprinque();
});

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
	lcSprinque.setFormStep(oneStepCheckout, data);
	if (!lcSprinque.checkBeforeSubmit()) {
		return false;
	}
	ev.preventDefault();
	lcSprinque.pcCheckoutForm.preventSubmit = true;
	lcSprinque.disablePayButton();
	lcSprinque.getChallenge(data);
	return false;
}, true);

hideSprinque = function(creditLimit) {
	if (creditLimit <= 0) {
		const element = document.querySelectorAll('[data-plugin-module="'+SPRINQUE_PAYMENT_MODULE+'"]');
		if (element.length > 0) {
			const parent = $(element).parent();
			parent.css("display", "none");
		}
	}
}

class LcSprinque {
	SPRINQUE_PAYMENT_MODULE = 'com.logicommerce.sprinque';
	pcButtonStep = '#paymentAndShippingBasketContinue';
	pcPaymentSystemId = 0;
	pcCheckoutForm = {};
	paymentSystemSelected = {};
	buyerId = "";
	creditStatus = "";
	creditLimit = 0;
	availableCreditLimit = 0;
	oneStepCheckout = false;
	firstTime = false;

	setDataConfig(dataConfig) {
		try {
			this.buyerId = dataConfig.buyerId ? dataConfig.buyerId:"";
			this.creditStatus = dataConfig.creditStatus ? dataConfig.creditStatus:"";
			this.creditLimit = dataConfig.creditLimit ? dataConfig.creditLimit:0;
			this.availableCreditLimit = dataConfig.availableCreditLimit? dataConfig.availableCreditLimit:0;
			this.firstTime = dataConfig.firstTime;
		} catch (error) {
			console.log(error);
		}
	}

	setFormStep(osc, data) {
		this.pcCheckoutForm = data;
		if (osc == true) {
			this.oneStepCheckout = osc;
			this.pcButtonStep = '#basketEndOrder';
			this.pcCheckoutForm = data.el.$form;
			CHECKOUT_URL = LC.global.routePaths.CHECKOUT;
		} else {
			this.pcCheckoutForm = data;
			this.pcButtonStep = '#paymentAndShippingBasketContinue';
			CHECKOUT_URL = LC.global.routePaths.CHECKOUT_PAYMENT_AND_SHIPPING;
		}
		return this.pcButtonStep;
	}

	checkShowSprinque() {
		if (this.firstTime == true) {
			return;
		}
		if (this.availableCreditLimit <= 0) {
			const element = document.querySelectorAll('[data-plugin-module="'+SPRINQUE_PAYMENT_MODULE+'"]');
			if (element.length > 0) {
				const parent = $(element).parent();
				parent.css("display", "none");
			}
		}
	}
	

	checkBeforeSubmit() {		
		if (typeof this.pcCheckoutForm.preventSubmit != "undefined" && this.pcCheckoutForm.preventSubmit) {
			this.enablePayButton();
			return false;
		}
		const selected = this.pcCheckoutForm.find('.basketSelectorPaymentInput:checked');
		if (selected.length == 0) {
			this.enablePayButton();
			return false;
		}
		this.paymentSystemSelected = selected.val() || 0;
		this.pcPaymentSystemId = JSON.parse(this.paymentSystemSelected).id;
		if (this.pcPaymentSystemId == 0) {
			this.enablePayButton();
			return false;
		}
		if (selected.attr("data-plugin-module") != SPRINQUE_PAYMENT_MODULE) {
			this.enablePayButton();
			return false;
		}
		return true;
	}

	disablePayButton() {
		$(this.pcButtonStep).addClass("loading");
		$(this.pcButtonStep).prop('disabled', true);
	}

	enablePayButton() {
		$(this.pcButtonStep).prop('disabled', false);
		$(this.pcButtonStep).removeClass("loading");
	}

	getChallenge(data) {
		var additonalData = { osc: this.oneStepCheckout };
		if (this.oneStepCheckout == true) {
			additonalData = { updateBasketRows: data.updateBasketRows, action: data.submitButton.val(), osc: this.oneStepCheckout };
		}
		fetch(LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP, {
			method: 'post',
			body: JSON.stringify($.extend(data.dataForm, additonalData)) 
		}).then(function(res) {
			fetch(LC.global.routePaths.CHECKOUT_END_ORDER, {
				method: 'post',
				headers: { 'content-type': 'application/json' }
			}).then(function(res) {
				return res.json();
			}).then(function(data) {
				Sprinque.open({
					token: data.token,
					env: data.env,
					lang: data.lang,
					buyerId: data.buyerId,
					initialValues: data.initialValues,
					onBuyerResponse: (buyer) => {
						lcBuyerId = buyer.buyer_id;
					},
					onClose: () => {
						window.location.href = CHECKOUT_URL;
					},
					onOrderCreated: order => {
						var params = "?sprinqueBuyerId=" + lcBuyerId
							+ "&sprinqueTransactionId=" + order.transaction_id
							+ "&transactionId=" + order.merchant_order_id;
						window.location.href = LC.global.routePaths.CHECKOUT_CONFIRM_ORDER + params;
					},
					order: data.order
            	});
        	});
		});
	}

	loadWidget(data) {
        Sprinque.open({
            token: data.token,
            env: data.env,
            lang: 'es', // 'en', 'es', 'de', 'fr', 'pl' are supported for now
            initialValues: data.initialValues,
            onBuyerResponse: (buyer) => {
            },
            onClose: () => {
                thgis.enablePayButton();
            },
            onOrderCreated: order => console.log('Order is created:', order),
			order: data.order
        });
	}

	getIframe(script) {
		let content = document.createElement("div");
		content.innerHTML = script;
		return content;
	}

	showIframe(method) {
		if (method == "1") {
			return true;
		} else {
			return false;
		}
	}
}
