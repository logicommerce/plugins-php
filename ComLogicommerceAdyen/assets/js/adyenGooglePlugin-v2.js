var lCadyenGPay = {};
var paymentDataGoogle = {};
var paymentValid = false;
LC.resources.addPluginListener('initializePaymentsCallback', function(form, oneStepCheckout) {
	lCadyenGPay = new LcAdyenGPay();	
	lCadyenGPay.ADYEN_PAYMENT_MODULE = 'com.logicommerce.adyen';
	if (!lCadyenGPay.checkCallBack(form)) {
		return false;
	}
	try {
		lCadyenGPay.setConfiguration();
		lCadyenGPay.showAdyenGoogle();
	}
	catch (err) {
		console.log("error : " + err);
	}
}, true);

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {	
	lCadyenGPay.setFormStep(oneStepCheckout, data);
	lCadyenGPay.disablePayButton();
	if (!lCadyenGPay.checkBeforeSubmit()) {
		return false;
	}
	lCadyenGPay.checkoutForm.preventSubmit = true;
	if (!lCadyenGPay.dataCreateGoogle.isAvailable()) {
		lCadyenGPay.errorValidation(LC.global.languageSheet.completePaymentInformation);
		lCadyenGPay.enablePayButton();
		return false;
	}
	lCadyenGPay.dataCreateGoogle.submit().then(() => {
		if (!paymentValid) {
			lCadyenGPay.errorValidation(LC.global.languageSheet.completePaymentInformation);
			lCadyenGPay.enablePayButton();
			return false;
		}
		lCadyenGPay.setAdyenData();
		lCadyenGPay.processEndOrder(oneStepCheckout, data);
		return false;
	})
	return false;
}, true);

class LcAdyenGPay {
	ADYEN_PAYMENT_MODULE = 'com.logicommerce.adyen';
	storePaymentMethod = false;
	dataCreateGoogle = {};
	checkoutGoogle = {};
	adyenTransactionId = "";
	buttonStep = '#paymentAndShippingBasketContinue';
	paymentType = "";
	paymentSystemId = 0;
	dataConfig = {};
	paymentMethods = {};
	checkoutForm = {};
	adyenData = {};
	paymentSystemSelected = {};
	configuration = {};

	checkCallBack(form) {
		const selected = form.find('.basketSelectorPaymentInput:checked');
		if (selected.length == 0) {
			return false;
		}
		const paymentSystemSelected = selected.val() || 0;
		this.paymentSystemId = JSON.parse(paymentSystemSelected).id;
		if (this.paymentSystemId == 0) {
			return false;
		}
		if (selected.attr("data-plugin-module") != this.ADYEN_PAYMENT_MODULE) {
			return false;
		}
		const adyenCheckoutAPIElementId = '#adyenCheckoutAPI' + this.paymentSystemId;	
		this.paymentMethods = $(adyenCheckoutAPIElementId).attr('data-payments');
		this.dataConfig = $(adyenCheckoutAPIElementId).attr('data-config');
		this.paymentType = $(adyenCheckoutAPIElementId).attr('data-method') || "card";
		if (!this.paymentMethods || !this.dataConfig) {
			return false;
		}
		if (this.paymentType != 'paywithgoogle' && this.paymentType != 'googlepay') {
			return false;
		}
		return true;
	}

	checkBeforeSubmit() {		
		if (typeof this.checkoutForm.preventSubmit != "undefined" && this.checkoutForm.preventSubmit) {
			this.enablePayButton();
			return false;
		}
		const selected = this.checkoutForm.find('.basketSelectorPaymentInput:checked');
		if (selected.length == 0) {
			this.enablePayButton();
			return false;
		}
		this.paymentSystemSelected = selected.val() || 0;
		this.paymentSystemId = JSON.parse(this.paymentSystemSelected).id;
		if (this.paymentSystemId == 0) {
			this.enablePayButton();
			return false;
		}
		if (selected.attr("data-plugin-module") != ADYEN_PAYMENT_MODULE) {
			this.enablePayButton();
			return false;
		}
		const adyenCheckoutAPIElementId = '#adyenCheckoutAPI' + this.paymentSystemId;	
		this.paymentType = $(adyenCheckoutAPIElementId).attr('data-method') || "card";
		if (this.paymentType != "paywithgoogle" && this.paymentType != 'googlepay') {
			this.enablePayButton();
			return false;
		}
		return true;
	}

	setAdyenData() {
		this.adyenData = JSON.parse(this.paymentSystemSelected);
		this.adyenData['additionalData'] = '{"module":"' + this.ADYEN_PAYMENT_MODULE + '","paymentData":'+ JSON.stringify(paymentDataGoogle)+'}';
	}

	googlePayLoad() {
		return new Promise((res,rej) => {
			var script = document.createElement("script");
			script.type = "text/javascript";
			script.onload = () => res()
			script.onerror = () => rej()
			script.src = "https://pay.google.com/gp/p/js/pay.js";
			document.head.appendChild(script);
		})
	}

	getEnvironment(environment) {
		if (environment.toLowerCase() == 'test') {
			return 'TEST';
		}
		return 'live';
	}

	async showAdyenGoogle() {
		this.checkoutGoogle = await AdyenCheckout();
		this.dataCreateGoogle = this.checkoutGoogle.create(this.paymentType, this.configuration);
		this.googlePayLoad().then(() => {
			this.dataCreateGoogle.mount("#adyen_" + this.paymentSystemId);
		});
	}

	setConfiguration() {	
		const config = JSON.parse(this.dataConfig);
		const merchant = this.getMerchant();
		this.configuration = {
			locale: config.locale,
			environment: this.getEnvironment(config.environment),
			countryCode: config.country,
			amount: {
				value: config.total,
				currency: config.currency
			},
			clientKey: config.clientKey,
			configuration: merchant,
			showPayButton: false
		};
		this.configuration.onSubmit = this.handleOnSubmitGoogle;
	}

	getMerchant() {
		const methods = JSON.parse(this.paymentMethods);
		let merchant = {};
		for (let m in methods.paymentMethods) {
			if (methods.paymentMethods[m].type == 'paywithgoogle') {
				merchant = methods.paymentMethods[m].configuration;
			}
		}
		return merchant;
	}

	setFormStep(oneStepCheckout, data) {
		this.checkoutForm = data;
		if(oneStepCheckout == true) {
			this.buttonStep = '#basketEndOrder';
			this.checkoutForm = data.el.$form;
		} else {
			this.buttonStep = '#paymentAndShippingBasketContinue';
		}
		return this.buttonStep;
	}
	
	handleOnSubmitGoogle = function (state, component) {
		if (state.data.paymentMethod && state.isValid) {
			paymentValid = true;
			paymentDataGoogle = state.data.paymentMethod;
		}
		storePaymentMethod = false;
		if (state.data.storePaymentMethod) {
			storePaymentMethod = state.data.storePaymentMethod;
		}
		paymentDataGoogle.storeDetails = storePaymentMethod;
	}
	
	handleOnError = function () {
		paymentValid = false;
	}
	
	errorValidation(message) {
		LC.notify(message, { type: 'danger' });
	}

	disablePayButton() {
		$(this.buttonStep).prop('disabled', true);
	}

	enablePayButton() {
		$(this.buttonStep).prop('disabled', false);
	}

	processEndOrder(oneStepCheckout, data) {
		$.post(
			LC.global.routePaths.BASKET_INTERNAL_SET_PAYMENT_SYSTEM,
			{
				data: JSON.stringify(this.adyenData),
			},
			(response) => {
				this.disablePayButton();
				if (response.data.response.success) {
					let additonalData = { osc: oneStepCheckout };
					if (oneStepCheckout == true) {
						additonalData = {
							updateBasketRows: data.updateBasketRows,
							action: data.submitButton.val(),
							osc: oneStepCheckout
						};
					}
					$.post(
						LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP,
						{
							data: JSON.stringify($.extend(data.dataForm, additonalData))
						},
						(response) => {
							window.location.href = LC.global.routePaths.CHECKOUT_END_ORDER;
						}).fail(function() {
							this.enablePayButton();
						});
				} else {
					window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
				}
			},
			'json',
		).fail(function(xhr, status, error) {
			this.enablePayButton();
		});
	}
}