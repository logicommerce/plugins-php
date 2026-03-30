(function () {
    let adyenAPayV6 = {};

    function init() {
        class LcAdyenAPayV6 extends LcAdyenBaseV6 {
            appleMethods = ['applepay'];
            dataCreateApple = {};
            checkoutApple = {};

            isValidPaymentType(paymentType) {
                return this.appleMethods.includes(paymentType);
            }

            checkApple() {
                if (!window.ApplePaySession) {
                    const elements = document.querySelectorAll('[data-method="applepay"]');
                    if (elements.length > 0) {
                        $(elements).parent().parent().css('display', 'none');
                    }
                }
            }

            async showAdyenApple() {
                this.checkoutApple = await AdyenWeb.AdyenCheckout(this.configuration);
                const merchant = this.getMerchant(null, this.appleMethods);
                const componentConfig = {
                    amount: {
                        value: this.parsedConfig.total,
                        currency: this.parsedConfig.currency
                    },
                    countryCode: this.parsedConfig.country,
                    configuration: {
                        merchantId: merchant.merchantId,
                        merchantName: merchant.merchantName
                    },
                    onSubmit: this.handleOnSubmitApple.bind(this)
                };
                this.dataCreateApple = new AdyenWeb.ApplePay(this.checkoutApple, componentConfig);
                this.dataCreateApple.isAvailable().then(() => {
                    this.dataCreateApple.mount('#adyen_' + this.paymentSystemId);
                }).catch(e => {
                    const elements = document.querySelectorAll('[data-method="applepay"]');
                    if (elements.length > 0) {
                        $(elements).parent().parent().css('display', 'none');
                    }
                    console.log('Apple Pay no disponible: ' + e);
                });
            }

            async handleOnSubmitApple(state, component, actions) {
                if (state.data.paymentMethod && state.isValid) {
                    const paymentData = state.data.paymentMethod;
                    if (state.data.storePaymentMethod) {
                        paymentData.storeDetails = state.data.storePaymentMethod;
                    }
                    this.setAdyenData(paymentData);
                    this.processEndOrder();
                    actions.resolve({ resultCode: 'Authorised' });
                    LC.CheckoutForm.prototype.loadingWindow(this.buttonStep, LC.global.languageSheet.ComLogicommerceAdyenV6ProcessingPayment);
                } else {
                    this.errorValidation(LC.global.languageSheet.completePaymentInformation);
                    this.enablePayButton();
                    actions.reject();
                }
            }
        }

        LC.resources.addPluginListener('initializePaymentsCallback', function (form) {
            adyenAPayV6 = new LcAdyenAPayV6();
            adyenAPayV6.checkApple();
            if (!adyenAPayV6.checkCallBack(form)) {
                return false;
            }
            try {
                adyenAPayV6.setConfiguration();
                adyenAPayV6.showAdyenApple();
            } catch (err) {
                console.log('error : ' + err);
            }
        }, true);

        LC.resources.addPluginListener('beforeSubmitEndOrder', function (ev, data, oneStepCheckout) {
            if (ev.beforeSubmitEndOrderHandled) {
                console.log('Evento ya gestionado por otro plugin, saltando...');
                return false;
            }
            adyenAPayV6.setFormStep(oneStepCheckout, data);
            if (!adyenAPayV6.checkBeforeSubmit()) return false;
            adyenAPayV6.disablePayButton();
            ev.beforeSubmitEndOrderHandled = true;
            adyenAPayV6.checkoutForm.preventSubmit = true;
            adyenAPayV6.dataCreateApple.submit();
            return false;
        }, true);
    }

    if (typeof LcAdyenBaseV6 !== 'undefined') {
        init();
    } else {
        document.addEventListener('LcAdyenBaseReady', init, { once: true });
    }
})();