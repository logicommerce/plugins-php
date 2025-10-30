var lcStripe = {};
var stripe = {};
var elements = {};

LC.resources.addPluginListener('initializePaymentsCallback', function(form, oneStepCheckout) {
    lcStripe = new LcStripe();
	lcStripe.STRIPE_PAYMENT_MODULE = 'com.logicommerce.stripe';
    if (!lcStripe.checkCallBack(form)) {
		return false;
	}
	try {
		lcStripe.showStripe();
	}
	catch (err) {
		console.log("error : " + err);
	}
}, true);

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
    lcStripe.setFormStep(oneStepCheckout, data);
	lcStripe.disablePayButton();
	if (!lcStripe.checkBeforeSubmit()) {
		return false;
	}
	lcStripe.checkoutForm.preventSubmit = true;
    lcStripe.disablePayButton();

    elements.submit().then(function(result) {
        lcStripe.processPayment(data);
    });
}, true);

class LcStripe {
	STRIPE_PAYMENT_MODULE = 'com.logicommerce.stripe';
	buttonStep = '#paymentAndShippingBasketContinue';
	paymentSystemId = 0;
	clientSecret = {};
	checkoutForm = {};
	paymentSystemSelected = {};
    dataConfig = {};
    oneStepCheckout = false;
    paymentMethod = "card";
    wallets = false;

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
		if (selected.attr("data-plugin-module") != this.STRIPE_PAYMENT_MODULE) {
			return false;
		}
		const stripeCheckoutAPIElementId = '#stripeCheckoutAPI' + this.paymentSystemId;	
		this.dataConfig = $(stripeCheckoutAPIElementId).attr('data-config');
		this.paymentMethod = $(stripeCheckoutAPIElementId).attr('data-method') || "card";
		if (!this.dataConfig || !this.paymentMethod) {
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
		if (selected.attr("data-plugin-module") != this.STRIPE_PAYMENT_MODULE) {
			this.enablePayButton();
			return false;
		}
		const stripeCheckoutAPIElementId = '#stripeCheckoutAPI' + this.paymentSystemId;
		this.paymentMethod = $(stripeCheckoutAPIElementId).attr('data-method') || "card";
		return true;
	}

	showStripe() {
        const config = JSON.parse(this.dataConfig);
        stripe = Stripe(config.publicKey);
        const options = {
            mode: 'payment',
            amount: config.total,
            currency: config.currency.toLowerCase(),
        };
        if (this.paymentMethod == 'link') {
            options.paymentMethodTypes = ['card', 'link'];
        } else {
            options.paymentMethodTypes = [this.paymentMethod];
        }
        elements = stripe.elements(options);
        const configElement = this.getConfigElement(config);
        const stripeElement = elements.create('payment', configElement);
        stripeElement.mount('#stripe_' + this.paymentSystemId);
	}

    getConfigElement(config) {
        var configElement = {};
        if (!config.wallets) {
            configElement.wallets= { googlePay: 'never', applePay: 'never' };
        }
        if (typeof(lcCommerceSession) !== 'undefined') {
            configElement.defaultValues = {
                billingDetails: {
                    email: lcCommerceSession.email,
                    name: lcCommerceSession.name,
                    phone: lcCommerceSession.phone
                },
            };
        }
        return configElement;
    }

	setFormStep(oneStepCheckout, data) {
		this.checkoutForm = data;
        this.oneStepCheckout = oneStepCheckout;
		if (oneStepCheckout == true) {
			this.buttonStep = '#basketEndOrder';
			this.checkoutForm = data.el.$form;
		} else {
			this.buttonStep = '#paymentAndShippingBasketContinue';
		}
		return this.buttonStep;
	}
    errorValidation(message) {
		LC.notify(message, { type: 'danger' });
	}

	disablePayButton() {
		$(this.buttonStep).addClass("loading");
		$(this.buttonStep).prop('disabled', true);
	}

	enablePayButton() {
		$(this.buttonStep).removeClass("loading");
		$(this.buttonStep).prop('disabled', false);
    }

	async processPayment(data) {
        var additonalData = {};
        if (this.oneStepCheckout == true) {
            additonalData = { updateBasketRows: data.updateBasketRows, action: data.submitButton.val(), osc: this.oneStepCheckout };
        } else {
            data.dataForm = this.fillDataForm(data);
            additonalData = { updateBasketRows: data.updateBasketRows, osc: this.oneStepCheckout };
        }
		let formData = new FormData();
		formData.append('data', JSON.stringify($.extend(data.dataForm, additonalData)));
		fetch(LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP, {
			method: 'post',
			body: formData
		}).then(function(res) {
			fetch(LC.global.routePaths.CHECKOUT_END_ORDER, {
				method: 'post',
				headers: { 'content-type': 'application/json' }
			}).then(function(res) {
				return res.json();
			}).then(function(data) {
				console.log(elements);
                const {error} = stripe.confirmPayment({
                    elements,
                    clientSecret: data.clientSecret,
                    confirmParams: {
                        return_url: data.returnUrl
                    }
                });
                if (error) {
                    console.log(error.message);
                }
        	});
		});
	}

    fillDataForm(form) {
        var arrDataForm = form.serializeArray();
        var dataForm = {};
        for (var i = 0; i < arrDataForm.length; i++) {
            if (!(arrDataForm[i].name in dataForm)) {
              dataForm[arrDataForm[i].name] = [];
            }
            dataForm[arrDataForm[i].name].push(arrDataForm[i].value);
        }
        for (var i in dataForm) {
          dataForm[i] = dataForm[i].join();
        }
        return dataForm;
    }

}