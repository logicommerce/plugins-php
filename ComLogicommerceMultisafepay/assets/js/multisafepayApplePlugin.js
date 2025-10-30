const MSP_APPLE_PAYMENT_MODULE = 'com.logicommerce.multisafepay';
var buttonStep = '#paymentAndShippingBasketContinue';
var mspContainer = '#MultiSafepayPayment_';
var paymentSystemSelectedId = 0;
var appleVersion = 10;
var ApplePayRequest = {};
var session = {};

LC.resources.addPluginListener('initializePaymentsCallback', function(form, oneStepCheckout) {
	
	if (oneStepCheckout) {
		buttonStep = '#basketEndOrder';
	}

	if (!window.ApplePaySession || !ApplePaySession.canMakePayments()) {
		var appleContainer = $('[data-config*="APPLEPAY"]');
		if (appleContainer != "undefined") {
			appleContainer.parent().parent().remove();
		}
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
	if (selected.attr("data-plugin-module") != MSP_APPLE_PAYMENT_MODULE) {
		return false;
	}
	var dataConfig = getDataAppleConfig(paymentSystemSelectedId);
	if (dataConfig.redirectGateway != 'APPLEPAY') {
		return false;
	}

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
	if (selected.attr("data-plugin-module") == MSP_APPLE_PAYMENT_MODULE) {

		var paymentSystemSelected = selected.val() || 0;
		paymentSystemSelectedId = JSON.parse(paymentSystemSelected).id;
		if (paymentSystemSelectedId == 0) {
			return false;
		}
		var dataConfig = getDataAppleConfig(paymentSystemSelectedId);
		console.log(dataConfig);
		if (dataConfig.redirectGateway != 'APPLEPAY') {
			return false;
		}
		$(buttonStep).prop('disabled', true);
		checkoutForm.preventSubmit = true;

		setMSPAppleConfig(dataConfig);
		session = new ApplePaySession(appleVersion, ApplePayRequest);		
		session.begin();

	}
	return false;
}, true);


session.onvalidatemerchant = function (event) {
	var validationUrl = event.validationURL;
	var originDomain = window.location.hostname;

	// Request an Apple Pay merchant session from your server
	// The server-side request requires the validationUrl and originDomain values

	// If you succesfully create a session from your server
	session.completeMerchantValidation("<apple-pay-payment-session-data>");
};


var getDataAppleConfig = function(paymentSystemId) {
	var MSPCheckoutAPIElementId = '#multisafepay_' + paymentSystemId;
	var dataConfig = $(MSPCheckoutAPIElementId).attr('data-config');
	return dataConfig = JSON.parse(dataConfig);
}

var setMSPAppleConfig = function(dataConfig) {
	var amount = dataConfig.total / 100;
	ApplePayRequest = {
		"countryCode": dataConfig.country,
		"currencyCode": dataConfig.currency,
		"merchantCapabilities": [
			"supports3DS"
		], 
		"supportedNetworks": [
			"amex",
			"maestro",
			"masterCard",
			"visa",
			"vPay"
		],
		"requiredBillingContactFields":[
			"postalAddress",
			"billingAddress"
		],
		"total":{
			"label": "Your Merchant Name",
			"type": "final",
			"amount": amount
		}
	};
}