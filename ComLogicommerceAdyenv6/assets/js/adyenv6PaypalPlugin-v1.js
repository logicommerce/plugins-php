(function () {
    let adyenPayPalV6 = {};

    function init() {
        class LcAdyenPayPalV6 extends LcAdyenBaseV6 {
            dataCreatePayPal = {};
            checkoutPayPal = {};

            isValidPaymentType(paymentType) {
                return paymentType === 'paypal';
            }

            setAdyenData(paymentData) {
                document.querySelectorAll('input[name="paymentSystem"]').forEach((elem) => {
                    if (elem.checked) {
                        this.paymentSystemSelected = elem.value || { id: 0 };
                    }
                });
                this.adyenData = JSON.parse(this.paymentSystemSelected);
                this.adyenData['additionalData'] = '{"module":"' + this.module + '","paymentData":' + JSON.stringify(paymentData) + '}';
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
                };
            }

            async showAdyenPayPal() {
                this.checkoutPayPal = await AdyenWeb.AdyenCheckout(this.configuration);
                const componentConfig = {
                    blockPayPalCreditButton: true,
                    blockPayPalPayLaterButton: true,
                    onClick: this.handleOnClick.bind(this),
                    onSubmit: this.handleOnSubmitPayPal.bind(this),
                    onAdditionalDetails: this.handleOnAdditionalDetails.bind(this),
                    onCancel: this.handleOnCancel.bind(this),
                    onError: this.handleOnError.bind(this)
                };
                this.dataCreatePayPal = new AdyenWeb.PayPal(this.checkoutPayPal, componentConfig);
                const containerId = 'adyen_' + this.paymentSystemId;
                document.getElementById(containerId).style.display = 'none';
                await this.dataCreatePayPal.mount('#' + containerId);
                await this.waitForPayPalButton(containerId);
                this.overlayPayPalButton(containerId);
            }

            waitForPayPalButton(containerId) {
                return new Promise((resolve, reject) => {
                    const selector = '#' + containerId + ' .adyen-checkout__paypal__button';
                    const button = document.querySelector(selector);
                    if (button) { resolve(button); return; }
                    const container = document.getElementById(containerId);
                    if (!container) { reject(new Error('Container de PayPal no encontrado')); return; }
                    const observer = new MutationObserver((mutations, obs) => {
                        const btn = document.querySelector(selector);
                        if (btn) { obs.disconnect(); resolve(btn); }
                    });
                    observer.observe(container, { childList: true, subtree: true });
                    setTimeout(() => {
                        observer.disconnect();
                        reject(new Error('Timeout esperando botón de PayPal'));
                    }, 5000);
                });
            }

            overlayPayPalButton(containerId) {
                const endOrderButton = document.getElementById(this.buttonStep.replace('#', ''));
                const paypalButton = document.querySelector('#' + containerId + ' .adyen-checkout__paypal__button');
                if (!endOrderButton || !paypalButton) { this.enablePayButton(); return; }
                const rect = endOrderButton.getBoundingClientRect();
                endOrderButton.style.pointerEvents = 'none';
                document.body.appendChild(paypalButton);
                this.setPaypalButtonStyles(paypalButton, rect);
                this.enablePayButton();
                this.repositionButtonListener(paypalButton, endOrderButton);
            }

            setPaypalButtonStyles(paypalButton, rect) {
                paypalButton.style.cssText = '';
                Object.assign(paypalButton.style, {
                    position: 'fixed',
                    top: rect.top + 'px',
                    left: rect.left + 'px',
                    width: rect.width + 'px',
                    height: rect.height + 'px',
                    margin: '0', padding: '0',
                    border: 'none', outline: 'none', boxShadow: 'none',
                    opacity: '0', backgroundColor: 'transparent',
                    pointerEvents: 'auto', cursor: 'pointer'
                });
                paypalButton.style.setProperty('z-index', '1000', 'important');
            }

            repositionButtonListener(paypalButton, endOrderButton) {
                const reposition = this.repositionButton.bind(this, paypalButton, endOrderButton);
                window.addEventListener('scroll', reposition, { passive: true });
                window.addEventListener('resize', reposition, { passive: true });
                setTimeout(() => {
                    window.removeEventListener('scroll', reposition);
                    window.removeEventListener('resize', reposition);
                }, 30000);
            }

            repositionButton(paypalButton, endOrderButton) {
                const rect = endOrderButton.getBoundingClientRect();
                paypalButton.style.top = rect.top + 'px';
                paypalButton.style.left = rect.left + 'px';
            }

            async handleOnClick(data, actions) {
                const buttonStepId = this.buttonStep.replace('#', '');
                const form = $('form.' + buttonStepId + ', #' + buttonStepId).closest('form');
                if (!form.isValid()) {
                    this.enablePayButton();
                    return actions.reject();
                }
                return new Promise((resolve, reject) => {
                    this._pendingPayPalValidation = { resolve, reject };
                    $('.' + buttonStepId).click();
                    setTimeout(() => {
                        if (this._pendingPayPalValidation) {
                            this._pendingPayPalValidation = null;
                            actions.reject();
                        }
                    }, 5000);
                }).then((result) => {
                    if (result === false) { this.enablePayButton(); return actions.reject(); }
                    actions.resolve();
                }).catch(() => actions.reject());
            }

            async handleOnCancel() {
                this.enablePayButton();
            }

            async handleOnAdditionalDetails(state, component, actions) {
                LC.CheckoutForm.prototype.loadingWindow(this.buttonStep, LC.global.languageSheet.ComLogicommerceAdyenV6ProcessingPayment);
                this.setAdyenData(state.data, this.paymentSystemSelected);
                const result = await this.processEndOrder();
                if (result?.type === 'form') {
                    actions.resolve({ resultCode: result.properties.adyenAction });
                    this.post(result.url, result.properties ?? {}, 'post');
                } else {
                    window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
                }
            }

            async handleOnSubmitPayPal(state, component, actions) {
                this.setAdyenData(state.data.paymentMethod, this.paymentSystemSelected);
                const result = await this.processEndOrder();
                if (!result?.resultCode) {
                    this.errorValidation(LC.global.languageSheet.completePaymentInformation);
                    this.enablePayButton();
                    return actions.reject();
                }
                const { resultCode, action, order, donationToken } = result.paymentResponse;
                actions.resolve({ resultCode, action, order, donationToken });
            }

            async handleOnError() {
                this.enablePayButton();
                window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
            }
		
			async runEndOrder() {
				const res = await fetch(LC.global.routePaths.CHECKOUT_END_ORDER, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' }
				});
				return await res.json();
			}
        }

        LC.resources.addPluginListener('initializePaymentsCallback', function (form, oneStepCheckout) {
            adyenPayPalV6 = new LcAdyenPayPalV6();
            adyenPayPalV6.setButtonStep(oneStepCheckout);
            if (!adyenPayPalV6.checkCallBack(form)) return false;
            adyenPayPalV6.disablePayButton();
            try {
                adyenPayPalV6.setConfiguration();
                adyenPayPalV6.showAdyenPayPal();
            } catch (err) {
                console.log('error : ' + err);
            }
        }, true);

        LC.resources.addPluginListener('beforeSubmitEndOrder', function (ev, data, oneStepCheckout) {
            if (ev.beforeSubmitEndOrderHandled) {
                console.log('Evento ya gestionado por otro plugin, saltando...');
                return false;
            }
            adyenPayPalV6.setFormStep(oneStepCheckout, data);
            if (!adyenPayPalV6.checkBeforeSubmit()) {
                document.querySelectorAll('.adyen-checkout__paypal__button .adyen-checkout__paypal__button--paypal')
                    .forEach(el => el.remove());
                if (adyenPayPalV6._pendingPayPalValidation) {
                    adyenPayPalV6._pendingPayPalValidation.reject();
                    adyenPayPalV6._pendingPayPalValidation = null;
                }
                return false;
            }
            adyenPayPalV6.disablePayButton();
            adyenPayPalV6.checkoutForm.preventSubmit = true;
            ev.beforeSubmitEndOrderHandled = true;
            if (adyenPayPalV6._pendingPayPalValidation) {
                adyenPayPalV6._pendingPayPalValidation.resolve();
                adyenPayPalV6._pendingPayPalValidation = null;
            }
            return false;
        }, true);
    }

    if (typeof LcAdyenBaseV6 !== 'undefined') {
        init();
    } else {
        document.addEventListener('LcAdyenBaseReady', init, { once: true });
    }
})();