(function (global) {

    class LcAdyenBaseV6 {
        module = 'com.logicommerce.adyenv6';
        adyenContainerId = '#adyenCheckoutAPI';
        isOneStepCheckout = false;
        buttonStep = '#paymentAndShippingBasketContinue';
        buttonStepDefault = '#paymentAndShippingBasketContinue';
        buttonStepOsc = '#basketEndOrder';
        paymentType = "";
        paymentSystemId = 0;
        dataConfig = {};
        paymentMethods = {};
        checkoutForm = {};
        adyenData = {};
        paymentSystemSelected = {};
        osCheckoutForm = {};
        configuration = {};
        parsedConfig = {};

        disablePayButton() {
            $(this.buttonStep).prop('disabled', true);
            $(this.buttonStep).parent().addClass('loading');
        }

        enablePayButton() {
            $(this.buttonStep).prop('disabled', false);
            $(this.buttonStep).parent().removeClass('loading');
        }

        setButtonStep(oneStepCheckout) {
            this.isOneStepCheckout = false;
            if (oneStepCheckout == true) {
                this.isOneStepCheckout = true;
                this.buttonStep = this.buttonStepOsc;
            } else {
                this.buttonStep = this.buttonStepDefault;
            }
            return this.buttonStep;
        }

        setFormStep(oneStepCheckout, data) {
            this.checkoutForm = data;
            this.osCheckoutForm = data;
            this.isOneStepCheckout = false;
            if (oneStepCheckout == true) {
                this.isOneStepCheckout = true;
                this.buttonStep = this.buttonStepOsc;
                this.checkoutForm = data.el.$form;
            } else {
                this.buttonStep = this.buttonStepDefault;
            }
            return this.buttonStep;
        }

        checkCallBack(form) {
            const selected = form.find('.basketSelectorPaymentInput:checked');
            if (selected.length == 0) {
                return false;
            } 
            const paymentSystemSelected = selected.val() || 0;
            this.paymentSystemId = JSON.parse(paymentSystemSelected).id;
            if (this.paymentSystemId == 0) return false;
            if (selected.attr('data-plugin-module') != this.module) {
                return false;
            }
            const $el = $(this.adyenContainerId + this.paymentSystemId);
            this.paymentMethods = $el.attr('data-payments');
            this.dataConfig = $el.attr('data-config');
            this.paymentType = $el.attr('data-method') || 'card';
            if (!this.paymentMethods || !this.dataConfig) {
                return false;
            }
            return this.isValidPaymentType(this.paymentType);
        }
    
        checkBeforeSubmit() {
            if (typeof this.checkoutForm.preventSubmit !== 'undefined' && this.checkoutForm.preventSubmit) {
                return false;
            }
            const selected = this.checkoutForm.find('.basketSelectorPaymentInput:checked');
            if (selected.length == 0) return false;

            this.paymentSystemSelected = selected.val() || 0;
            this.paymentSystemId = JSON.parse(this.paymentSystemSelected).id;
            if (this.paymentSystemId == 0) {
                return false;
            }
            if (selected.attr('data-plugin-module') != this.module) {
                return false;
            }
            this.paymentType = $(this.adyenContainerId + this.paymentSystemId).attr('data-method') || 'card';
            return this.isValidPaymentType(this.paymentType);
        }

        isValidPaymentType(paymentType) {
            return true;
        }

        setConfiguration() {
            this.parsedConfig = JSON.parse(this.dataConfig);
            this.configuration = {
                locale: this.parseLanguage(this.parsedConfig.locale),
                environment: this.getEnvironment(this.parsedConfig.environment),
                countryCode: this.parsedConfig.country,
                amount: {
                    value: this.parsedConfig.total,
                    currency: this.parsedConfig.currency
                },
                clientKey: this.parsedConfig.clientKey,
                showPayButton: false
            };
        }

        setAdyenData(paymentData) {
            this.adyenData = JSON.parse(this.paymentSystemSelected);
            this.adyenData['additionalData'] = '{"module":"' + this.module + '","paymentData":' + JSON.stringify(paymentData) + '}';
        }

        getMerchant(methods, validTypes) {
            const parsed = JSON.parse(this.paymentMethods);
            let merchant = {};
            for (let m in parsed.paymentMethods) {
                if (validTypes.includes(parsed.paymentMethods[m].type)) {
                    merchant = parsed.paymentMethods[m].configuration;
                }
            }
            return merchant;
        }

        async processEndOrder() {
            try {
                const paymentResponse = await this.setPaymentSystem();
                this.disablePayButton();
                if (!paymentResponse.data.response.success) {
                    window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
                    return;
                }
                await this.runNextStep();
                return await this.runEndOrder();
            } catch (e) {
                this.enablePayButton();
            }
        }

        async setPaymentSystem() {
			$('#paymentSteps').html(LC.global.languageSheet.ComLogicommerceAdyenV6SetPaymentMethod);
            const res = await fetch(LC.global.routePaths.BASKET_INTERNAL_SET_PAYMENT_SYSTEM, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'data=' + encodeURIComponent(JSON.stringify(this.adyenData))
            });
            return res.json();
        }

        getNextStepPayload() {
			let additonalData = {};
			let baseFormData = {};
			if (this.isOneStepCheckout === true) {
				additonalData = {
					updateBasketRows: this.osCheckoutForm.updateBasketRows,
					action: this.osCheckoutForm.submitButton.val(),
					osc: this.isOneStepCheckout,
				};
				baseFormData = this.osCheckoutForm.dataForm || {};
			} else {
				baseFormData = this.fillDataForm(this.osCheckoutForm);
				additonalData = { updateBasketRows: this.osCheckoutForm.updateBasketRows, osc: this.isOneStepCheckout };
			}
			return { data: JSON.stringify($.extend(baseFormData, additonalData)) };
		}

        async runNextStep() {
			$('#paymentSteps').html(LC.global.languageSheet.ComLogicommerceAdyenV6ProcessingOrder);
            let additionalData = this.getNextStepPayload();
            const postData = Object.assign({}, this.osCheckoutForm.dataForm, additionalData);
            const res = await fetch(LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'data=' + encodeURIComponent(JSON.stringify(postData))
            });
            return res.json();
        }

        async runEndOrder() {
			$('#paymentSteps').html(LC.global.languageSheet.ComLogicommerceAdyenV6CreatingOrder);
            const res = await fetch(LC.global.routePaths.CHECKOUT_END_ORDER, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await res.json();
            this.handleEndOrderResponse(data);
            return data;
        }

        handleEndOrderResponse(data) {
            const { type, url, properties, paymentResponse, transactionId } = data;
            switch (type) {
                case 'redirect':
                    window.location.href = url;
                    break;
                case 'form':
                case 'no_pay':
                case 'offline':
                    this.post(url, properties ?? {}, 'post');
                    break;
                case 'action':
                    this.adyenTransactionId = transactionId;
                    const action = paymentResponse?.action ?? data;
                    this.component?.handleAction?.(action);
                    break;
                default:
                    window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
                    break;
            }
        }

        resetElements() {
            const elements = document.querySelectorAll('.adyen-checkout__paypal__button--paypal');
            elements.forEach(el => el.remove());
        }

        getEnvironment(environment) {
            return environment.toLowerCase() === 'test' ? 'test' : 'live';
        }

        parseLanguage(language) {
            switch (language) {
                case 'es': return 'es-ES';
                case 'en': return 'en-US';
                case 'fr': return 'fr-FR';
                case 'de': return 'de-DE';
                case 'it': return 'it-IT';
                default:   return 'es-ES';
            }
        }

        errorValidation(message) {
            LC.notify(message, { type: 'danger' });
        }

        post(path, params, method = 'post') {
            const form = document.createElement('form');
            form.method = method;
            form.action = path;
            for (const key in params) {
                if (Object.prototype.hasOwnProperty.call(params, key)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = params[key];
                    form.appendChild(input);
                }
            }
            document.body.appendChild(form);
            form.submit();
        }

        parseTotalAmount(total, currency) {
            return currency === 'JPY' ? Math.trunc(total) : total;
        }
    }

    global.LcAdyenBaseV6 = LcAdyenBaseV6;
    document.dispatchEvent(new CustomEvent('LcAdyenBaseReady'));

})(window);