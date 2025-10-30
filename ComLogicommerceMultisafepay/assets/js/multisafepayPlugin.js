const MSP_PAYMENT_MODULE = 'com.logicommerce.multisafepay';
var mspPaymentData = {};
var buttonStep = '#paymentAndShippingBasketContinue';
var mspContainer = '#MultiSafepayPayment_';
var paymentSystemSelectedId = 0;

LC.resources.addPluginListener('initializePaymentsCallback', function(form, oneStepCheckout) {
	mspPaymentData = {};
	mspComponent = {};
	if (oneStepCheckout) {
		buttonStep = '#basketEndOrder';
	}
	var selected = form.find('.basketSelectorPaymentInput:checked');
	if (selected.length == 0) {
		return false;
	}
	var paymentSystemSelected = selected.val() || 0;
	paymentSystemSelectedId = JSON.parse(paymentSystemSelected).id;

	if (paymentSystemSelectedId == 0) {
		return false;
	}
	if (selected.attr("data-plugin-module") != MSP_PAYMENT_MODULE) {
		return false;
	}
	var dataConfig = getDataConfig(paymentSystemSelectedId);
	if (dataConfig.redirectGateway == 'REDIRECT' 
		|| dataConfig.redirectGateway == 'GOOGLEPAY' 
		|| dataConfig.redirectGateway == 'APPLEPAY') {
		return false;
	}

	if (!checkGateways(dataConfig.redirectGateway)) {
		return false;
	}
	const orderData = setMSPConfig(dataConfig);
	createMspComponent(dataConfig, orderData, mspContainer, paymentSystemSelectedId);
}, true);

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
	var checkoutForm = data;
	if (oneStepCheckout) {
		checkoutForm = data.el.$form;
		buttonStep = '#basketEndOrder';
	} else {
		buttonStep = '#paymentAndShippingBasketContinue';
	}
	$(buttonStep).prop('disabled', true);	
	if (typeof checkoutForm.preventSubmit != "undefined" && checkoutForm.preventSubmit) {
		return false;
	}
	if (checkoutForm.find('.basketSelectorPaymentInput:checked').length == 0) {
		return false;
	}
	var selected = checkoutForm.find('.basketSelectorPaymentInput:checked');
	if (selected.attr("data-plugin-module") == MSP_PAYMENT_MODULE) {
		var paymentSystemSelected = selected.val() || 0;
		paymentSystemSelectedId = JSON.parse(paymentSystemSelected).id;
		if (paymentSystemSelectedId == 0) {
			return false;
		}
		var dataConfig = getDataConfig(paymentSystemSelectedId);
		if (dataConfig.redirectGateway == 'GOOGLEPAY') {
			return false;
		}
		$(buttonStep).prop('disabled', true);
		checkoutForm.preventSubmit = true;
		if(!checkGateways(dataConfig.redirectGateway)){
			checkoutForm.preventSubmit = false;
			return false;
		}
		if (Object.keys(mspPaymentData).length === 0) {
			$(buttonStep).prop('disabled', false);
			checkoutForm.activeButton = true;
			errorValidation(LC.global.languageSheet.completePaymentInformation);
		  	return false;
		}
		mspPaymentData = mspComponent.getPaymentData();
		var mspData = JSON.parse(paymentSystemSelected);
		mspData['additionalData'] = '{"module":"' + MSP_PAYMENT_MODULE + '","paymentData":'+ JSON.stringify(mspPaymentData)+'}';		
		/*
		if (mspComponent.hasErrors()) {
			errorValidation(LC.global.languageSheet.completePaymentInformation);
			return false;
		}*/
		$.post(
			LC.global.routePaths.BASKET_INTERNAL_SET_PAYMENT_SYSTEM,
			{
				data: JSON.stringify(mspData),
			},
			(response) => {
				$(buttonStep).prop('disabled', true);
				if (response.data.response.success) {
					var arrDataForm = checkoutForm.serializeArray();
                    this.dataForm = {};
                    for (var i = 0; i < arrDataForm.length; i++) {
                    	if (!(arrDataForm[i].name in this.dataForm)) {
							this.dataForm[arrDataForm[i].name] = [];
						}
                        this.dataForm[arrDataForm[i].name].push(arrDataForm[i].value);
                    }
                    for (var i in this.dataForm) {
						this.dataForm[i] = this.dataForm[i].join();
					}
					var additionalData = { osc: oneStepCheckout };
					if (oneStepCheckout) {
						additionalData = { updateBasketRows: data.updateBasketRows, action: data.submitButton.val(), osc: oneStepCheckout };
					}
                    $.post(
                        LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP, 
                        {
							data: JSON.stringify($.extend(data.dataForm, additionalData)) 
                        },
                        (response) => {
							window.location.href = LC.global.routePaths.CHECKOUT_END_ORDER;
						}).fail(function() {
							$(buttonStep).prop('disabled', false);
						});
				} else {
					window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
				}
			},				
			'json',
		).fail(function(xhr, status, error) {
			$(buttonStep).prop('disabled', false);
		});
	}
	return false;
}, true);

var setMSPConfig = function(dataConfig) {
	const orderConfig = {
		currency: dataConfig.currency,
		amount: dataConfig.total,
		customer: {
			locale: dataConfig.locale,
			country: dataConfig.country
		},
		template : {
			settings: {
				embed_mode: true
			}
		}
	};
	if(dataConfig.tokenizable){
		orderConfig.customer.reference = dataConfig.reference;
		orderConfig.recurring = {model: 'cardOnFile'};
	}
	return orderConfig;
}

var createMspComponent = function(dataConfig, orderConfig, mspContainer, paymentSystemSelectedId) {
	mspComponent = new MultiSafepay({
		env: dataConfig.environment,
		apiToken: dataConfig.apiTokenMSP,
		order: orderConfig
	});

	mspComponent.init('payment', {
		container: mspContainer + paymentSystemSelectedId,
		gateway: dataConfig.redirectGateway,
		onLoad: state => {
			mspPaymentData = mspComponent.getPaymentData();
		},
		onError: state => {
			console.log('onError', state);
		},
		onValidation: state => {
			mspPaymentData = mspComponent.getPaymentData();
			if(state.valid === true){
				paymentValid = true;
			}
			else {
				paymentValid = false;
			}
		}
	});
}

var paymentSystemSelect = function(form, container) {
	var selected = form.find(container);
	var paymentSystemSelected = selected.val() || 0;
	var paymentSystemId = JSON.parse(paymentSystemSelected).id;
	return paymentSystemId;
}

var getDataConfig = function(paymentSystemId) {
	var MSPCheckoutAPIElementId = '#multisafepay_' + paymentSystemId;
	var dataConfig = $(MSPCheckoutAPIElementId).attr('data-config');
	return dataConfig = JSON.parse(dataConfig);
}

var checkGateways = function(gateway) {
	switch (gateway) {
		case 'BANKTRANS':
			return true;
		case 'MISTERCASH':
			return true;
		case 'CREDITCARD':
			return true;
		case 'IDEAL':
			return true;
		case 'PAYPAL':
			return true;
		case 'DIRDEB':
			return true;
		case 'DIRECTBANK':
			return true;
		default:
			return false;
	}
}