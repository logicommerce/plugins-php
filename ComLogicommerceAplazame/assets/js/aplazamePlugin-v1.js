LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
	if (ev.beforeSubmitEndOrderHandled) {
		return false;
	}
	let lcAplazame = new LcAplazame(oneStepCheckout);
	let checkoutForm = lcAplazame.getCheckoutForm(data);
	let selected = checkoutForm.find('.basketSelectorPaymentInput:checked');
	if (selected.attr('data-plugin-module') != lcAplazame.APLAZAME_PAYMENT_MODULE) {
		return false;
	}
	if (!lcAplazame.setPaymentSystemId(selected)) {
		return false;
	}
	ev.beforeSubmitEndOrderHandled = true;
	ev.preventDefault();
	checkoutForm.preventSubmit = true;
	lcAplazame.disablePayButton();
	if (oneStepCheckout != true) {
		data.dataForm = lcAplazame.fillDataForm(data);
	}
	lcAplazame.processOrder(data);
}, true);

class LcAplazame {

	APLAZAME_PAYMENT_MODULE = 'com.logicommerce.aplazame';
	SUCCESS_STATUSES = ['success', 'pending'];

	buttonStep = '#paymentAndShippingBasketContinue';
	paymentSystemId = 0;
	oneStepCheckout = false;

	constructor(oneStepCheckout) {
		this.oneStepCheckout = oneStepCheckout == true;
		if (this.oneStepCheckout) {
			this.buttonStep = '#basketEndOrder';
		}
	}

	getCheckoutForm(data) {
		if (this.oneStepCheckout) {
			return data.el.$form;
		}
		return data;
	}

	setPaymentSystemId(selected) {
		try {
			this.paymentSystemId = JSON.parse(selected.val() || '{}').id || 0;
		} catch (error) {
			this.paymentSystemId = 0;
		}
		return this.paymentSystemId != 0;
	}

	processOrder(data) {
		let formData = new FormData();
		formData.append('data', JSON.stringify($.extend(data.dataForm, this.getAdditionalData(data))));
		fetch(LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP, {
			method: 'POST',
			body: formData
		}).then(() => {
			this.endOrder();
		}).catch(() => {
			this.enablePayButton();
		});
	}

	getAdditionalData(data) {
		let additionalData = {
			updateBasketRows: data.updateBasketRows,
			osc: this.oneStepCheckout
		};
		if (this.oneStepCheckout) {
			additionalData.action = data.submitButton.val();
		}
		return additionalData;
	}

	endOrder() {
		fetch(LC.global.routePaths.CHECKOUT_END_ORDER, {
			method: 'POST',
			headers: { 'content-type': 'application/json' }
		}).then((response) => {
			if (response.redirected && response.url.includes(LC.global.routePaths.CHECKOUT_DENIED_ORDER)) {
				return this.redirect(response.url);
			}
			return response.text().then((text) => this.openCheckout(this.parseWidgetData(text)));
		}).catch(() => {
			this.redirect(LC.global.routePaths.CHECKOUT_DENIED_ORDER);
		});
	}

	parseWidgetData(text) {
		try {
			let widgetData = JSON.parse(text.trim());
			return widgetData && widgetData.checkoutId ? widgetData : null;
		} catch (error) {
			return null;
		}
	}

	openCheckout(widgetData) {
		if (widgetData == null) {
			this.redirect(LC.global.routePaths.CHECKOUT_DENIED_ORDER);
			return;
		}
		(window.aplazame = window.aplazame || []).push((aplazame) => {
			this.enablePayButton();
			aplazame.checkout(widgetData.checkoutId, {
				onClose: (resultStatus) => this.onClose(widgetData, resultStatus)
			});
		});
	}

	onClose(widgetData, resultStatus) {
		if (this.SUCCESS_STATUSES.includes(resultStatus)) {
			this.redirect(widgetData.successUrl);
			return;
		}
		this.redirect(widgetData.koUrl || LC.global.routePaths.CHECKOUT_DENIED_ORDER);
	}

	redirect(url) {
		window.location.href = url;
	}

	fillDataForm(form) {
		let dataForm = {};
		for (const element of form.serializeArray()) {
			if (!(element.name in dataForm)) {
				dataForm[element.name] = [];
			}
			dataForm[element.name].push(element.value);
		}
		for (let name in dataForm) {
			dataForm[name] = dataForm[name].join();
		}
		return dataForm;
	}

	disablePayButton() {
		$(this.buttonStep).addClass('loading');
		$(this.buttonStep).prop('disabled', true);
	}

	enablePayButton() {
		$(this.buttonStep).removeClass('loading');
		$(this.buttonStep).prop('disabled', false);
	}
}
