var ADYEN_PAYMENT_MODULE = 'com.logicommerce.adyen';
var paymentValid = false;
var paymentData = {};
var storePaymentMethod = false;
var dataCreate = {};
var checkout = {};
var adyenTransactionId = "";
var buttonStep = '#paymentAndShippingBasketContinue';

LC.resources.addPluginListener('initializePaymentsCallback', function(form, oneStepCheckout) {
	ADYEN_PAYMENT_MODULE = 'com.logicommerce.adyen';
	if(oneStepCheckout) {
		buttonStep = '#basketEndOrder';
	}
	if (form.find('.basketSelectorPaymentInput:checked').length == 0) {
		return false;
	}
	dataCreate = {};
	checkout = {};
	paymentData = {};
	var selected = form.find('.basketSelectorPaymentInput:checked');
	var paymentSystemSelected = selected.val() || 0;
	var paymentSystemId = JSON.parse(paymentSystemSelected).id;	
	if (paymentSystemId == 0) {
		return false;
	}
	if (selected.attr("data-plugin-module") != ADYEN_PAYMENT_MODULE) {
		return false;
	}
	var adyenCheckoutAPIElementId = '#adyenCheckoutAPI' + paymentSystemId;
	if (!$(adyenCheckoutAPIElementId).attr('data-payments') || !$(adyenCheckoutAPIElementId).attr('data-config')) { 
		return false; 
	}
	var paymentType = $(adyenCheckoutAPIElementId).attr('data-method') || "card";
	if (paymentType == "paywithgoogle" || paymentType == 'googlepay' || paymentType == "applepay") {
		return false;
	}
	var paymentMethods = $(adyenCheckoutAPIElementId).attr('data-payments');
	paymentMethods = JSON.parse(paymentMethods);
	var dataConfig = $(adyenCheckoutAPIElementId).attr('data-config');
	dataConfig = JSON.parse(dataConfig);
	var configuration = {
		locale: dataConfig.locale,
		environment: dataConfig.environment,
		countryCode: dataConfig.country,
		amount: {
			value: dataConfig.amount,
			currency: dataConfig.currency
		},
		clientKey: dataConfig.clientKey,
		paymentMethodsResponse: paymentMethods,
		onChange: handleOnChange,
		onSubmit: handleOnSubmit,
		onAdditionalDetails: handleOnAdditionalDetails,
		onError:handleOnError,
		showPayButton:false
	};
	var adyenData = {}
	if (typeof adyenDataConfig != "undefined" && typeof adyenDataConfig[paymentType] != "undefined") {
		adyenData = adyenDataConfig[paymentType];
	}
	else {
		var adyenDataConfig = {};
		adyenDataConfig["card"] = {enableStoreDetails: true,hasHolderName: true,holderNameRequired: true};
		adyenDataConfig["paypal"] = {enableStoreDetails: true};
		if (typeof adyenDataConfig[paymentType] != "undefined") {
			adyenData = adyenDataConfig[paymentType];
		}
	}
	try {
		showAdyen(configuration, paymentType, adyenData, paymentSystemId);
	}
	catch(err) {
		console.log("error : " + err);
	}
}, true);

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout, data2) {
	var checkoutForm = data;
	if(oneStepCheckout == true) {
		buttonStep = 'basketEndOrder';
		checkoutForm = data.el.$form;
	} else {
		buttonStep = 'paymentAndShippingBasketContinue';
	}
	//$(buttonStep).prop('disabled', true);
	if (typeof checkoutForm.preventSubmit != "undefined" && checkoutForm.preventSubmit) {
		return false;
	}
	if (checkoutForm.find('.basketSelectorPaymentInput:checked').length == 0) {
		return false;
	}
	var eleman = document.getElementById(buttonStep);
    eleman.disabled = true;
	var selected = checkoutForm.find('.basketSelectorPaymentInput:checked');
	var paymentSystemSelected = selected.val() || 0;
	var paymentSystemId = JSON.parse(paymentSystemSelected).id;
	if (paymentSystemId == 0) {
		return false;
	}
	var adyenCheckoutAPIElementId = '#adyenCheckoutAPI' + paymentSystemId;
	var paymentType = $(adyenCheckoutAPIElementId).attr('data-method') || "card";
	if (paymentType == "paywithgoogle" || paymentType == 'googlepay' || paymentType == "applepay") {
		return false;
	}
	if (selected.attr("data-plugin-module") == ADYEN_PAYMENT_MODULE) {
		checkoutForm.preventSubmit = true;
		if (Object.keys(paymentData).length === 0) {
			dataCreate.onChange();
		}
		var adyenData = JSON.parse(paymentSystemSelected);
		console.log(JSON.stringify(paymentData))
		console.log(paymentData)
		adyenData['additionalData'] = '{"module":"' + ADYEN_PAYMENT_MODULE + '","paymentData":'+ JSON.stringify(paymentData)+'}';
		if (!paymentValid) {
			errorValidation(LC.global.languageSheet.completePaymentInformation);
			$(buttonStep).prop('disabled', false);
			eleman.disabled = false;
		  	return false;
		}
		//$(buttonStep).prop('disabled', true);
		$.post(
			LC.global.routePaths.BASKET_INTERNAL_SET_PAYMENT_SYSTEM,
			{
				data: JSON.stringify(adyenData),
			},
			(response) => {
				//$(buttonStep).prop('disabled', true);
				if (response.data.response.success) {
					var additonalData = { osc: oneStepCheckout };
					if (oneStepCheckout == true) {
						additonalData = { updateBasketRows: data.updateBasketRows, action: data.submitButton.val(), osc: oneStepCheckout };
					}
                    $.post(
                        LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP,
                        {
							data: JSON.stringify($.extend(data.dataForm, additonalData))
                        },
                        (response) => {
							if(paymentData.type == 'mbway') {
								$.post(
									LC.global.routePaths.CHECKOUT_END_ORDER,
									(response) => {
										try {
											var action = JSON.parse(response);
											adyenTransactionId = action.transactionId;
											var actionParsed = { paymentData: action.paymentData, paymentMethodType: action.paymentMethodType, type: action.type };
											dataCreate.handleAction(actionParsed);
										} catch(err) {
											window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
										}
									}
								);
							}
							else {
								window.location.href = LC.global.routePaths.CHECKOUT_END_ORDER;
							}
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

var handleOnChange = function (state, component) {
	if (state.data.paymentMethod && state.isValid) {
		paymentValid = true;
		paymentData = state.data.paymentMethod;
	}
	storePaymentMethod = false;
	if (state.data.storePaymentMethod) {
		storePaymentMethod = state.data.storePaymentMethod;
	}
	paymentData.storeDetails = storePaymentMethod;
}

var handleOnSubmit = function (state, component) {
	handleOnChange(state, component);
	$(buttonStep).click();
}

var handleOnAdditionalDetails = function (state, component) {
	if(adyenTransactionId == "") {
		window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
	}	
	var params = {
		details: JSON.stringify(state.data.details),
		paymentData: JSON.stringify(state.data.paymentData),
		adyenTransactionId: adyenTransactionId,
		transactionId: adyenTransactionId
	};
	post(LC.global.routePaths.CHECKOUT_CONFIRM_ORDER, params, "post");
}

var handleOnError = function () {
	paymentValid = false;
}

var errorValidation = function (message) {
	LC.notify(message, { type: 'danger' });
}

async function showAdyen(configuration, paymentType, adyenData, paymentSystemId) {
	checkout = await AdyenCheckout(configuration);
	dataCreate = checkout.create(paymentType, adyenData).mount("#adyen_" + paymentSystemId);	
	const storedPaymentMethods = checkout.paymentMethodsResponse.storedPaymentMethods;
	if (storedPaymentMethods.length > 0 && paymentType == 'card') {
		for (var i = 0; i < storedPaymentMethods.length; i++) {
			var paymentMethod = storedPaymentMethods[i];
			var oneClick = checkout.create(paymentType, paymentMethod).mount("#stored-card-" + paymentMethod.id);
			if (oneClick.icon) {
				$("<img>", {
					"src" : oneClick.icon
				}).appendTo($("#stored-icon-" + paymentMethod.id));
			}
			if (oneClick.displayName) {
				$("#stored-displayName-" + paymentMethod.id).html(oneClick.displayName);
			}
		}
	}
}

var post = function(path, params, method='post') {
	const form = document.createElement('form');
	form.method = method;
	form.action = path;  
	for (const key in params) {
		if (params.hasOwnProperty(key)) {
			const hiddenField = document.createElement('input');
			hiddenField.type = 'hidden';
			hiddenField.name = key;
			hiddenField.value = params[key];
			form.appendChild(hiddenField);
		}
	}  
	document.body.appendChild(form);
	form.submit();
}