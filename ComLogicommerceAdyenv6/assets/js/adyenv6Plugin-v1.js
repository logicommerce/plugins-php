(function () {
	function init() {
		class LcAdyenComponentsV6 extends LcAdyenBaseV6 {
			constructor(opts = {}) {
				super();
				this.module = opts.moduleName || "com.logicommerce.adyenv6";
				this.buttonStepDefault = "#paymentAndShippingBasketContinue";
				this.buttonStepOsc = "#basketEndOrder";			
				this.buttonStep = '#paymentAndShippingBasketContinue';
				this.adyenContainerId = '#adyenCheckoutAPI';
				this.paymentSystemId = 0;
				this.paymentType = "";
				this.dataConfig = {};
				this.onInitializePayments = this.onInitializePayments.bind(this);
				this.onBeforeSubmitEndOrder = this.onBeforeSubmitEndOrder.bind(this);
				this.handleOnChange = this.handleOnChange.bind(this);
				this.handleOnSubmit = this.handleOnSubmit.bind(this);
				this.handleOnAdditionalDetails = this.handleOnAdditionalDetails.bind(this);
				this.handleOnError = this.handleOnError.bind(this);
				this.adyenData = {};
				this.paymentSystemSelected = {};
				this.paymentMethods = {};
				this.adyenDataConfig = {};
				this.configuration = {};
				this.reset();
			}

			reset() {
				this.paymentValid = false;
				this.paymentData = {};
				this.paymentMethods = {};
				this.paymentSystemSelected = {};
				this.configuration = {};
				this.storePaymentMethod = false;
				this.dataConfig = {};
				this.checkout = null;
				this.component = null;
				this.adyenTransactionId = "";
				this.buttonStep = this.buttonStepDefault;
				this.adyenData = {};
				this.paymentType = "";
				this.excluedesPaymentTypes = ["paywithgoogle", "googlepay", "applepay", "paypal"];
				this.resetElements();
			}

			register() {
				LC.resources.addPluginListener("initializePaymentsCallback", this.onInitializePayments, true);
				LC.resources.addPluginListener("beforeSubmitEndOrder", this.onBeforeSubmitEndOrder, true);
			}

			onInitializePayments(form, oneStepCheckout) {
				this.reset();
				this.setButtonStep(oneStepCheckout);
				if (!this.checkCallBack(form)){
					return false;
				}
				try {
					this.setConfiguration();
					this.setAdyenDataConfig();
					this.showAdyen();
				} catch (err) {
					console.log("error : " + err);
				}
			}

			async showAdyen() {
				this.checkout = await AdyenWeb.AdyenCheckout(this.configuration);
				this.component = this.createComponentV6();
				this.component.mount("#adyen_" + this.paymentSystemId);
				if (this.paymentType === "card") {
					const stored = this.checkout?.paymentMethodsResponse?.storedPaymentMethods || [];
					if (stored.length) {
						this.showStoredCards(stored);
					} 
				}
			}

			showStoredCards(storedPaymentMethods) {
				for (let i = 0; i < storedPaymentMethods.length; i++) {
					const pm = storedPaymentMethods[i];
					const oneClick = new AdyenWeb.Card(this.checkout, pm).mount("#stored-card-" + pm.id);
					if (oneClick?.icon) {
						$("<img>", { src: oneClick.icon }).appendTo($("#stored-icon-" + pm.id));
					}
					if (oneClick?.displayName) {
						$("#stored-displayName-" + pm.id).html(oneClick.displayName);
					}
				}
			}

			createComponentV6() {
				const map = {
					card: AdyenWeb?.Card,
					paypal: AdyenWeb?.PayPal,
					klarna: AdyenWeb?.Klarna,
					klarna_paynow: AdyenWeb?.Klarna,
					klarna_account: AdyenWeb?.Klarna,
					multibanco: AdyenWeb?.Multibanco,
					mbway: AdyenWeb?.MBWay,
					ideal: AdyenWeb?.Redirect,
					clearpay: AdyenWeb?.Redirect,
					paysafecard: AdyenWeb?.Redirect,
				};
				const AdyenConstructor = map[this.paymentType];
				if (!AdyenConstructor) {
					throw new Error(`No hay constructor v6 mapeado para paymentType='${this.paymentType}'.`);
				}
				return new AdyenConstructor(this.checkout, this.adyenDataConfig || {});
			}
		
			isValidPaymentType(paymentType) {
				return !this.excluedesPaymentTypes.includes(paymentType);
			}

			onBeforeSubmitEndOrder(ev, data, oneStepCheckout) {
				if (ev.beforeSubmitEndOrderHandled) {
					return false;
				}
				this.setFormStep(oneStepCheckout, data);
				if (!this.checkBeforeSubmit()) {
					return false;
				}
				this.disablePayButton();
				ev.beforeSubmitEndOrderHandled = true;
				if (this.paymentType !== "mbway") {
					LC.CheckoutForm.prototype.loadingWindow(this.buttonStep, LC.global.languageSheet.ComLogicommerceAdyenV6ProcessingPayment);
				}
				this.checkoutForm.preventSubmit = true;
				this.ensurePaymentData();
				if (!this.validatePaymentOrNotify()) {
					return false;
				}
				this.setAdyenData(this.paymentData);
				this.runCheckoutFlow({ oneStepCheckout, data });
				return false;
			}

			ensurePaymentData() {
				if (Object.keys(this.paymentData).length !== 0) {
					return;
				}
				try {
					this.component?.onChange?.();
				} catch (e) {
					console.log(e);
				}
			}

			validatePaymentOrNotify() {
				if (this.paymentValid) {
					return true;
				}
				this.errorValidation(LC.global.languageSheet.completePaymentInformation);
				this.enablePayButton();
				return false;
			}

			async runCheckoutFlow() {
				this.disablePayButton();
				try {

					const paymentResponse = await this.setPaymentSystem();
					if (!this.isSetPaymentSystemSuccess(paymentResponse)) {
						window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
						return;
					}
					await this.runNextStep();
					await this.runEndOrder();
				} catch (err) {
					window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
				}
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
					case 'await':
						this.adyenTransactionId = transactionId;
						const awaitAction = paymentResponse?.action ?? data;
						this.component?.handleAction?.({
							paymentData: awaitAction.paymentData,
							paymentMethodType: awaitAction.paymentMethodType,
							type: awaitAction.type,
						});
						break;
					default:
						window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
						break;
				}
			}

			isSetPaymentSystemSuccess(response) {
				return !!response?.data?.response?.success;
			}

			handleOnChange(state, component) {
				if (state?.data?.paymentMethod && state?.isValid) {
					this.paymentValid = true;
					this.paymentData = state.data.paymentMethod;
				}
				this.storePaymentMethod = !!state?.data?.storePaymentMethod;
				if (this.paymentData && typeof this.paymentData === "object") {
					this.paymentData.storeDetails = this.storePaymentMethod;
				}
			}

			handleOnSubmit(state, component) {
				this.handleOnChange(state, component);
				$(this.buttonStep).click();
			}

			handleOnAdditionalDetails(state, component) {
				if (!this.adyenTransactionId) {
					window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
					return;
				}
				const params = {
					details: JSON.stringify(state?.data?.details),
					paymentData: JSON.stringify(state?.data?.paymentData),
					adyenTransactionId: this.adyenTransactionId,
					transactionId: this.adyenTransactionId,
				};
				this.post(LC.global.routePaths.CHECKOUT_CONFIRM_ORDER, params, "post");
			}

			handleOnError() {
				this.paymentValid = false;
			}

			setConfiguration() {
				this.parsedConfig = JSON.parse(this.dataConfig);
				this.configuration = {
					locale: this.parseLanguage(this.parsedConfig.locale),
					environment: this.getEnvironment(this.parsedConfig.environment),
					countryCode: this.parsedConfig.country,
					amount: { value: this.parsedConfig.total, currency: this.parsedConfig.currency },
					showPayButton: false,
					clientKey: this.parsedConfig.clientKey,
					paymentMethodsResponse: JSON.parse(this.paymentMethods),
					onChange: this.handleOnChange,
					onSubmit: this.handleOnSubmit,
					onAdditionalDetails: this.handleOnAdditionalDetails,
					onError: this.handleOnError,
				};
			}

			setAdyenDataConfig() {
				const fallback = {
					card: { enableStoreDetails: true, hasHolderName: true },
					paypal: { configuration: { intent: "capture" } },
					klarna_paynow: { type: "klarna_paynow" },
					klarna_account: { type: "klarna_account" },
					klarna: { type: "klarna" },
					ideal: { type: "ideal" },
					clearpay: { type: "clearpay" },
					paysafecard: { type: "paysafecard" },
				};
				if (typeof fallback[this.paymentType] !== "undefined") {
					this.adyenDataConfig = fallback[this.paymentType];
				}
			}
		}
		const adyenV6 = new LcAdyenComponentsV6();
		adyenV6.register();
	}
    if (typeof LcAdyenBaseV6 !== 'undefined') {
        init();
    } else {
        document.addEventListener('LcAdyenBaseReady', init, { once: true });
    }
})();