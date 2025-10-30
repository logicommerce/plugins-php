var lcAdyenAPay = {};
LC.resources.addPluginListener('initializePaymentsCallback', function(form, oneStepCheckout) {
	lcAdyenAPay = new LcAdyenAPay();
	lcAdyenAPay.ADYEN_PAYMENT_MODULE = 'com.logicommerce.adyen';
	lcAdyenAPay.checkApple();
	if (!lcAdyenAPay.checkCallBack(form)) {
		return false;
	}
	try {
		lcAdyenAPay.setConfiguration();
		lcAdyenAPay.showAdyenApple();
	}
	catch (err) {
		console.log("error : " + err);
	}
}, true);

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {	
	lcAdyenAPay.setFormStep(oneStepCheckout, data);
	lcAdyenAPay.disablePayButton();
	lcAdyenAPay.setPaymentSystem();
	if (!lcAdyenAPay.checkBeforeSubmit()) {
		return false;
	}
	lcAdyenAPay.checkoutForm.preventSubmit = true;
	if (!lcAdyenAPay.dataCreateApple.isAvailable()) {
		lcAdyenAPay.errorValidation(LC.global.languageSheet.completePaymentInformation);
		lcAdyenAPay.enablePayButton();
		return false;
	}
	lcAdyenAPay.dataCreateApple.submit();
	return false;
}, true);

var handleOnSubmitApple = function (state, component) {
	if (state.data.paymentMethod && state.isValid) {
		let paymentDataApple = state.data.paymentMethod;
		let storePaymentMethod = false;
		if (state.data.storePaymentMethod) {
			storePaymentMethod = state.data.storePaymentMethod;
		}
		paymentDataApple.storeDetails = storePaymentMethod;
		lcAdyenAPay.setAdyenData(paymentDataApple);
		lcAdyenAPay.processEndOrder();
	} else {
		window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
	}
}

class LcAdyenAPay {
	ADYEN_PAYMENT_MODULE = 'com.logicommerce.adyen';
	isOneStepCheckout = false;
	storePaymentMethod = false;
	dataCreateApple = {};
	checkoutApple = {};
	adyenTransactionId = "";
	buttonStep = '#paymentAndShippingBasketContinue';
	paymentType = "";
	paymentSystemId = 0;
	dataConfig = {};
	paymentMethods = {};
	checkoutForm = {};
	osCheckoutForm = {};
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
		if (this.paymentType != 'applepay') {
			return false;
		}
		return true;
	}

	setPaymentSystem() {
		const selected = this.checkoutForm.find('.basketSelectorPaymentInput:checked');
		this.paymentSystemSelected = selected.val() || 0;
	}

	getPaymentSystem() {
		return this.paymentSystemSelected;
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
		if (this.paymentType != "applepay") {
			this.enablePayButton();
			return false;
		}
		return true;
	}

	setAdyenData(paymentDataApple) {
		this.adyenData = JSON.parse(this.paymentSystemSelected);
		this.adyenData['additionalData'] = '{"module":"' + this.ADYEN_PAYMENT_MODULE + '","paymentData":'+ JSON.stringify(paymentDataApple)+'}';
	}

	getEnvironment(environment) {
		if (environment.toLowerCase() == 'test') {
			return 'TEST';
		}
		return 'live';
	}

	async showAdyenApple() {
		this.checkoutApple = await AdyenCheckout(this.configuration);
		this.dataCreateApple = this.checkoutApple.create(this.paymentType, this.applePayConfiguration);
		this.dataCreateApple.isAvailable().then(() => {
			this.dataCreateApple.mount("#adyen_" + this.paymentSystemId);
		}).catch(e => {
			let element = document.querySelectorAll('[data-method="applepay"]');
			element.parentElement.parentElement.hidden = true;
			console.log("Apple pay is no available. " + e);
		});
	}

	checkApple() {
		if (!window.ApplePaySession) {		
			const element = document.querySelectorAll('[data-method="applepay"]');
			if (element.length > 0) {
				const parent = $(element).parent().parent();
				parent.css("display", "none");
			}
		}
	}

	setConfiguration() {
		const config = JSON.parse(this.dataConfig);
		const merchant = this.getMerchant();
		this.applePayConfiguration = {
			amount: {
				value: config.total,
				currency: config.currency
			},
			countryCode: config.country,
			configuration: {
				merchantId: merchant.merchantId,
				merchantName: merchant.merchantName
			},
			onSubmit: handleOnSubmitApple
		};
		this.configuration = {
			environment: this.getEnvironment(config.environment),
			showPayButton: false,
			locale: config.locale,
			clientKey: config.clientKey,
			paymentMethodsConfiguration: {
				applepay: this.applePayConfiguration
			}
		};
	}

	getMerchant() {
		const methods = JSON.parse(this.paymentMethods);
		let merchant = {};
		for (let m in methods.paymentMethods) {
			if (methods.paymentMethods[m].type == 'applepay') {
				merchant = methods.paymentMethods[m].configuration;
			}
		}
		return merchant;
	}

	setFormStep(oneStepCheckout, data) {
		this.checkoutForm = data;
		this.osCheckoutForm = data;
		this.isOneStepCheckout = false;
		if (oneStepCheckout == true) {
			this.isOneStepCheckout = true;
			this.buttonStep = '#basketEndOrder';
			this.checkoutForm = data.el.$form;
		} else {
			this.buttonStep = '#paymentAndShippingBasketContinue';
		}
		return this.buttonStep;
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

	async processEndOrder() {
		$.post(
			LC.global.routePaths.BASKET_INTERNAL_SET_PAYMENT_SYSTEM,
			{
				data: JSON.stringify(this.adyenData),
			},
			(response) => {
				this.disablePayButton();
				if (response.data.response.success) {
					let additonalData = { osc: this.isOneStepCheckout };
					if (this.isOneStepCheckout == true) {
						additonalData = {
							updateBasketRows: this.osCheckoutForm.updateBasketRows,
							action: this.osCheckoutForm.submitButton.val(),
							osc: this.isOneStepCheckout
						};
					}
					$.post(
						LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP,
						{
							data: JSON.stringify($.extend(this.osCheckoutForm.dataForm, additonalData))
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