LC.resources.addPluginListener('initializePaymentsBefore', function(form, oneStepCheckout) {
	if (typeof Sequra == "undefined" || !window.Sequra) {
		return false;
	}
	if (oneStepCheckout) {
		Sequra.onLoad(function() { Sequra.refreshComponents(); });
	}
}, true);

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
	console.log(oneStepCheckout);
	let lcSequra = new LcSequra();
	lcSequra.setOneStepCheckout(oneStepCheckout);
	lcSequra.setButtonStep();
	let checkoutForm = lcSequra.getFormData(data);
	lcSequra.disablePayButton();
	let selected = checkoutForm.find('.basketSelectorPaymentInput:checked');
	if (selected.attr("data-plugin-module") != lcSequra.SEQURA_PAYMENT_MODULE) {
		lcSequra.enablePayButton();
		return false;
	}
	lcSequra.setPaymentSystemId(selected);
	if (lcSequra.paymentSystemId == 0) {
		lcSequra.enablePayButton();
		return false;
	}
	lcSequra.disablePayButton();
	ev.preventDefault();
	checkoutForm.preventSubmit = true;
	if (oneStepCheckout != true) {
		data.dataForm = lcSequra.fillDataForm(data);
	}
	lcSequra.procesOrder(data);
}, true);

class LcSequra {

	SEQURA_PAYMENT_MODULE = 'com.logicommerce.sequra';
	buttonStep = '#paymentAndShippingBasketContinue';
	paymentSystemId = 0;
	oneStepCheckout = false;

	setOneStepCheckout(oneStepCheckout) {
		this.oneStepCheckout = oneStepCheckout;
	}

	setButtonStep() {
		this.buttonStep = '#paymentAndShippingBasketContinue';
		if (this.oneStepCheckout == true) {
			this.buttonStep = '#basketEndOrder';
		}
	}

	getFormData(data) {
		if (this.oneStepCheckout == true) {
			return data.el.$form;
		}
		return data;
	}

	setPaymentSystemId(selected) {
		let paymentSystemSelected = selected.val() || 0;
		this.paymentSystemId = JSON.parse(paymentSystemSelected).id;
	}

	procesOrder(data) {
		let additonalData = this.getAdditionalData(data);
		fetch(LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP, {
			method: 'post',
			body: JSON.stringify($.extend(data.dataForm, additonalData))
		}).then(() => {
			this.endOrder(this.paymentSystemId);
		}).catch(() => {
			$(this.buttonStep).prop('disabled', false);
		});
	}

	getAdditionalData(data) {
		let additionalData = {
			updateBasketRows: data.updateBasketRows,
			osc: this.oneStepCheckout
		}
		if (this.oneStepCheckout == true) {
			additionalData.action = data.submitButton.val();
		}
		return additionalData;
	}

	endOrder(paymentSystemId) {
		fetch(LC.global.routePaths.CHECKOUT_END_ORDER, {
			method: 'post',
			headers: { 'content-type': 'application/json' }
		}).then((res) => {
			return res.text();
		}).then((data) => {
			this.paymentCallback(data, paymentSystemId);
		});
	}

	paymentCallback(response, paymentSystemId) {
		if (response.length > 0 && !response.includes("deniedOrder")) {
			$(".basketPaymentIframe-id" + paymentSystemId).html(response);
			$(".basketPaymentIframe-id" + paymentSystemId).css("display", "block");
			setTimeout(function() {
				window.SequraFormInstance.show();
				window.SequraFormInstance.setCloseCallback(() => { window.location.reload(); });
				$(this.buttonStep).removeClass("loading");
			}, 1000);
		}
		else {
			window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
		}
	}

	fillDataForm(form) {
		let arrDataForm = form.serializeArray();
		let dataForm = {};
		for (const element of arrDataForm) {
			if (!(element.name in dataForm)) {
			dataForm[element.name] = [];
			}
			dataForm[element.name].push(element.value);
		}
		for (let i in dataForm) {
			dataForm[i] = dataForm[i].join();
		}
		return dataForm;
	}

	disablePayButton() {
		$(this.buttonStep).addClass("loading");
		$(this.buttonStep).prop('disabled', true);
	}

	enablePayButton() {
		$(this.buttonStep).removeClass("loading");
		$(this.buttonStep).prop('disabled', false);
    }
}