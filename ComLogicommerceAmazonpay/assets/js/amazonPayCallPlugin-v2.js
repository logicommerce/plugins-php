var AMAZON_PAYMENT_MODULE = 'com.logicommerce.amazonpay';
var selected = $('input[name="paymentSystem"]:checked');
var lcAmazonPayCall = {};
var lcBuyerId = "";
var CHECKOUT_URL = LC.global.routePaths.CHECKOUT;

LC.resources.addPluginListener('initializePaymentsBefore', function(data) {
	lcAmazonPayCall = new LcAmazonPayCall();
	lcAmazonPayCall.AMAZON_PAYMENT_MODULE = 'com.logicommerce.amazonpay';
}, true);

LC.resources.addPluginListener('beforeExpressCheckoutRedirect', function(ev, data, lcData) {
	if (lcData.pluginModule != AMAZON_PAYMENT_MODULE) {
		return false;
	}
	lcAmazonPayCall = new LcAmazonPayCall();
	lcAmazonPayCall.AMAZON_PAYMENT_MODULE = 'com.logicommerce.amazonpay';
	const lcAmazonPayCheckout = new LcAmazonPay();
	if (lcData.action == 'update') {
		ev.preventDefault();
		lcData.preventSubmit = true;
		lcAmazonPayCheckout.changeAction(lcData.id);
	} else if (lcData.action == 'cancel') {
		lcAmazonPayCheckout.cancelAction();
	}
	
}, true);

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
	if (lcAmazonPayCall.setFormStep == undefined) {
		return false;
	}
	lcAmazonPayCall.setFormStep(oneStepCheckout, data);
	if (!lcAmazonPayCall.checkBeforeSubmit()) {
		return false;
	}
	ev.preventDefault();
	if (oneStepCheckout == true) {
		data.el.$form.preventSubmit = true;
	} else {
		data.preventSubmit = true;
	}
	lcAmazonPayCall.disablePayButton();
	lcAmazonPayCall.loadAmazonPay(data);
}, true);

class LcAmazonPayCall {
	AMAZON_PAYMENT_MODULE = 'com.logicommerce.amazonpay';
	pcButtonStep = '#paymentAndShippingBasketContinue';
	pcPaymentSystemId = 0;
	pcCheckoutForm = {};
	paymentSystemSelected = {};
	oneStepCheckout = false;
	firstTime = true;

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
		if (selected.attr("data-plugin-module") != AMAZON_PAYMENT_MODULE) {
			this.enablePayButton();
			return false;
		}
		let deliverySelected = this.pcCheckoutForm.find('.shippingTypeSelector:checked');
		let deliveryType = "SHIPPING";
		if (deliverySelected.length > 0) {
			deliveryType = deliverySelected.attr('data-lc-delivery-type');
		}
        if (selected.attr("data-lc-express-checkout") == "true" && deliveryType != 'PICKING') {
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

	loadAmazonPay(data) {
		let additonalData = { osc: this.oneStepCheckout };
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
                let signature = data.signature;
                let payload = data.payload;
                let publicKeyId = data.publicKey;
                let merchantId = data.merchantId;
				let accountOrigin = data.accountOrigin;
                const lcAmazonPayCheckout = new LcAmazonPay();
                lcAmazonPayCheckout.loadPayButtonCheckout(merchantId, publicKeyId, payload, signature, accountOrigin);
        	});
		});
	}
}