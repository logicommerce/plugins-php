const MSP_GOOGLE_PAYMENT_MODULE = 'com.logicommerce.multisafepay';
var buttonStep = '#paymentAndShippingBasketContinue';
var mspContainer = '#MultiSafepayPayment_';
var paymentSystemSelectedId = 0;
var paymentsClient = {};
var paymentDataRequest = {};
var mspGoogleData = {};

LC.resources.addPluginListener('initializePaymentsCallback', function(form, oneStepCheckout) {
	paymentsClient = {};
	paymentDataRequest = {};
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
	if (selected.attr("data-plugin-module") != MSP_GOOGLE_PAYMENT_MODULE) {
		return false;
	}
	var dataGoogleConfig = getDataGoogleConfig(paymentSystemSelectedId);
	if (dataGoogleConfig.redirectGateway != 'GOOGLEPAY') {
		return false;
	}
	googlePayLoad()
		.then(() => {
			dataGoogle(dataGoogleConfig);
		});
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
	if (selected.attr("data-plugin-module") == MSP_GOOGLE_PAYMENT_MODULE) {
		var paymentSystemSelected = selected.val() || 0;
		paymentSystemSelectedId = JSON.parse(paymentSystemSelected).id;
		if (paymentSystemSelectedId == 0) {
			return false;
		}
		var dataConfig = getDataGoogleConfig(paymentSystemSelectedId);
		if (dataConfig.redirectGateway != 'GOOGLEPAY') {
			return false;
		}
		$(buttonStep).prop('disabled', true);
		checkoutForm.preventSubmit = true;
		var paymentSystemSelected = selected.val() || 0;
		var paymentSystemId = JSON.parse(paymentSystemSelected).id;
		if (paymentSystemId == 0) {
			return false;
		}		
		paymentsClient.loadPaymentData(paymentDataRequest)
			.then(function(paymentData) {
				mspGoogleData = JSON.parse(paymentSystemSelected);
				var payment_token = {"payment_token":paymentData.paymentMethodData.tokenizationData.token};
				mspGoogleData['additionalData'] = '{"module":"' + MSP_GOOGLE_PAYMENT_MODULE + '","paymentData":'+ JSON.stringify(payment_token)+'}';
				processPayment(mspGoogleData, data);
				return false;
			})
			.catch(function(err){
				$(buttonStep).prop('disabled', false);
			});
	}
	return false;
}, true);

var processPayment = function(mspGoogleData, data) {
	$.post(
		LC.global.routePaths.BASKET_INTERNAL_SET_PAYMENT_SYSTEM,
		{
			data: JSON.stringify(mspGoogleData),
		},
		(response) => {
			$(buttonStep).prop('disabled', true);
			if (response.data.response.success) {
				var additionalData = { osc: oneStepCheckout };
				if (oneStepCheckout) {
					additionalData = {
						updateBasketRows: data.updateBasketRows, 
						action: data.submitButton.val(), 
						osc: oneStepCheckout 
					};
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
};

var dataGoogle = function(dataGoogleConfig) {
	const baseRequest = {
		apiVersion: 2,
		apiVersionMinor: 0
	};
	const tokenizationSpecification = {
		type: 'PAYMENT_GATEWAY',
		parameters: {
			'gateway': 'multisafepay',
			'gatewayMerchantId': dataGoogleConfig.accoutId
		}
	};
	const allowedCardNetworks = ["MASTERCARD", "VISA"];
	const allowedCardAuthMethods = ["CRYPTOGRAM_3DS", "PAN_ONLY"];
	const baseCardPaymentMethod = {
		type: 'CARD',
		parameters: {
			allowedAuthMethods: allowedCardAuthMethods,
			allowedCardNetworks: allowedCardNetworks
		}
	};
	const cardPaymentMethod = Object.assign(
		{tokenizationSpecification: tokenizationSpecification},
		baseCardPaymentMethod
	);
	var environment = getEnvironment(dataGoogleConfig.environment);
	paymentsClient = new google.payments.api.PaymentsClient({environment: environment});
	const isReadyToPayRequest = Object.assign({}, baseRequest);
	isReadyToPayRequest.allowedPaymentMethods = [baseCardPaymentMethod];
	paymentDataRequest = getGooglePaymentDataRequest(dataGoogleConfig, baseRequest, cardPaymentMethod);
};

var googlePayLoad = function() {
	return new Promise((res,rej) => {
		var script = document.createElement("script");
		script.type = "text/javascript";
		script.onload = () => res()
		script.onerror = () => rej()
		script.src = "https://pay.google.com/gp/p/js/pay.js";
		document.head.appendChild(script);
	})
};

var getGooglePaymentDataRequest = function(dataGoogleConfig, baseRequest, cardPaymentMethod) {
	var totalPrice = dataGoogleConfig.total / 100;
    const paymentDataRequest = Object.assign({}, baseRequest);
    paymentDataRequest.allowedPaymentMethods = [cardPaymentMethod];
    paymentDataRequest.transactionInfo = {
        totalPriceStatus: 'FINAL',
        totalPrice: totalPrice.toString(),
        currencyCode: dataGoogleConfig.currency,
        countryCode: dataGoogleConfig.country
    };
    paymentDataRequest.merchantInfo = {
        merchantName: dataGoogleConfig.gMerchantName,
        merchantId: dataGoogleConfig.gMerchantId
    };
    return paymentDataRequest;
};

var getDataGoogleConfig = function(paymentSystemId) {
	var MSPCheckoutAPIElementId = '#multisafepay_' + paymentSystemId;
	var dataGoogleConfig = $(MSPCheckoutAPIElementId).attr('data-config');
	return dataGoogleConfig = JSON.parse(dataGoogleConfig);
};

var getEnvironment = function(environment){
	if(environment == 'test') {
		return 'TEST';
	}
	return 'PRODUCTION';
};