
(function () {
    let adyenGPayV6 = {};

    function init() {
        class LcAdyenGPayV6 extends LcAdyenBaseV6 {
            googleMethods = ['paywithgoogle', 'googlepay'];
            dataCreateGoogle = {};
            checkoutGoogle = {};

            isValidPaymentType(paymentType) {
                return this.googleMethods.includes(paymentType);
            }

            googlePayLoad() {
                return new Promise((res, rej) => {
                    const script = document.createElement('script');
                    script.type = 'text/javascript';
                    script.onload = () => res();
                    script.onerror = () => rej();
                    script.src = 'https://pay.google.com/gp/p/js/pay.js';
                    document.head.appendChild(script);
                });
            }

            async showAdyenGoogle() {
                this.checkoutGoogle = await AdyenWeb.AdyenCheckout(this.configuration);
                const merchant = this.getMerchant(null, this.googleMethods);
                const componentConfig = {
                    configuration: merchant,
                    onSubmit: this.handleOnSubmitGoogle.bind(this),
                    onError: this.handleOnError.bind(this)
                };
                this.dataCreateGoogle = new AdyenWeb.GooglePay(this.checkoutGoogle, componentConfig);
                this.googlePayLoad().then(() => {
                    this.dataCreateGoogle.mount('#adyen_' + this.paymentSystemId);
                });
            }

            async handleOnSubmitGoogle(state, component, actions) {
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

            handleOnError(error) {
                if (error.name === 'CANCEL' || error.statusCode === 'CANCELLED') {
                    this.enablePayButton();
                } else {
                    this.errorValidation('Error al procesar Google Pay');
                    this.enablePayButton();
                }
            }
        }

        LC.resources.addPluginListener('initializePaymentsCallback', function (form) {
            adyenGPayV6 = new LcAdyenGPayV6();
            if (!adyenGPayV6.checkCallBack(form)) return false;
            try {
                adyenGPayV6.setConfiguration();
                adyenGPayV6.showAdyenGoogle();
            } catch (err) {
                console.log('error : ' + err);
            }
        }, true);

        LC.resources.addPluginListener('beforeSubmitEndOrder', function (ev, data, oneStepCheckout) {
            if (ev.beforeSubmitEndOrderHandled) {
                console.log('Evento ya gestionado por otro plugin, saltando...');
                return false;
            }
            adyenGPayV6.setFormStep(oneStepCheckout, data);
            if (!adyenGPayV6.checkBeforeSubmit()) return false;
            adyenGPayV6.disablePayButton();
            ev.beforeSubmitEndOrderHandled = true;
            adyenGPayV6.checkoutForm.preventSubmit = true;
            adyenGPayV6.dataCreateGoogle.submit();
            return false;
        }, true);
    }

    if (typeof LcAdyenBaseV6 !== 'undefined') {
        init();
    } else {
        document.addEventListener('LcAdyenBaseReady', init, { once: true });
    }
})();