var PAYCOMET_PAYMENT_MODULE = 'com.logicommerce.paycomet';
var selected = $('input[name="paymentSystem"]:checked');
var lCPaycometPlugin = {};

LC.resources.addPluginListener('initializePaymentsCallback', function(form, oneStepCheckout) {
	lCPaycometPlugin = new LcPaycometPlugin();
	lCPaycometPlugin.PAYCOMET_PAYMENT_MODULE = 'com.logicommerce.paycomet';
}, true);

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
	lCPaycometPlugin.setFormStep(oneStepCheckout, data);
	if (!lCPaycometPlugin.checkBeforeSubmit()) {
		return false;
	}
	ev.preventDefault();
	lCPaycometPlugin.pcCheckoutForm.preventSubmit = true;
	lCPaycometPlugin.disablePayButton();
	lCPaycometPlugin.getChallenge();
	return false;
}, true);

class LcPaycometPlugin {
	PAYCOMET_PAYMENT_MODULE = 'com.logicommerce.paycomet';
	pcButtonStep = '#paymentAndShippingBasketContinue';
	pcPaymentSystemId = 0;
	pcCheckoutForm = {};
	paymentSystemSelected = {};
	CHECKOUT_URL = LC.global.routePaths.CHECKOUT;

	setFormStep(oneStepCheckout, data) {
		this.pcCheckoutForm = data;
		if (oneStepCheckout == true) {
			this.pcButtonStep = '#basketEndOrder';
			this.pcCheckoutForm = data.el.$form;
			this.CHECKOUT_URL = LC.global.routePaths.CHECKOUT;
		} else {
			this.pcButtonStep = '#paymentAndShippingBasketContinue';
			this.CHECKOUT_URL = LC.global.routePaths.CHECKOUT_PAYMENT_AND_SHIPPING;
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
		if (selected.attr("data-plugin-module") != PAYCOMET_PAYMENT_MODULE) {
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

	getChallenge() {
		fetch(LC.global.routePaths.CHECKOUT_END_ORDER, {
			method: 'post',
			headers: { 'content-type': 'application/json' }
		}).then(function(res) {
			return res.json();
		}).then(function(data) {
			if (data.errorCode == 0 && data.challengeUrl != null) {
				if (lCPaycometPlugin.showIframe(data.method)) {
					lCPaycometPlugin.loadWidget(data.challengeUrl);
				} else {
					window.location.href = data.challengeUrl;
				}
			} else {
				window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
			}
		});
	}

	loadWidget(url) {
		let source = this.getIframe(url);
		$('<span/>').appendTo($(document.body)).box({
			uid: 'modalPaycommet',
			triggerOnClick: false,
			showFooter: false,
			source: source,
			keepSrc: true,
			size: 'medium',
			type: 'html'
		});
		$('#modalPaycommet').on('hidden.bs.modal', function() {
			window.location.href = lCPaycometPlugin.CHECKOUT_URL;
		});
	}

	getIframe(url) {
		let content = document.createElement("div");
		content.innerHTML = `<div id="head-Paycommet-popup"></div>
			<div id="content-Paycommet-popup">
				<iframe src="`+url+`" sandbox="allow-top-navigation allow-scripts allow-same-origin allow-forms" onLoad="resizeIframeToFitContent(this);"></iframe>
			</div>
			<div id="footer-Paycommet-popup"></div>`;
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

function resizeIframeToFitContent( iFrame ) {
	iFrame.height = "330px";
}
