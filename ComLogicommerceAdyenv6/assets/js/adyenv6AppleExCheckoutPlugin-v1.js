(function (global) {

	function init() {
		class LcAdyenApplePayExpress extends LcAdyenApplePayBaseExpress {

			constructor(settings) {
				super(settings);
			}

			handleLcBuyProductCallback(ctx, data, status, event) {
				this.serverTotals = data.data.data.totals;
				this.serverTaxes = data.data.data.taxes || [];
				ctx.instance.tmpTotal = Math.round(Number(this.serverTotals.total) * 100);
				ctx.resolve();
			}

			async getConfiguration() {
				const paymentMethodsResponse = await this.getPaymentMethods();
				this.configuration = {
					clientKey: this.clientKey,
					environment: this.environment,
					amount: {
						value: this.getAdyenAmount(this.tmpTotal, this.currencyCode),
						currency: this.currencyCode,
					},
					countryCode: this.countryCode,
					paymentMethodsResponse: JSON.parse('{"paymentMethods":' + paymentMethodsResponse + '}')
				};
				return this.configuration;
			}

			async render() {
				const configuration = await this.getConfiguration();
				const checkout = await this.getOrCreateCheckout(configuration);
				const checkoutApple = new window.AdyenWeb.ApplePay(checkout, {
					countryCode: this.countryCode,
					isExpress: true,
					requiredBillingContactFields: ["postalAddress", "email", "name", "phone"],
					requiredShippingContactFields: ["postalAddress", "email", "name", "phone"],
					onClick: this.onClickHandle.bind(this),
					onPaymentMethodSelected: this.onPaymentMethodSelectedHandle.bind(this),
					onShippingContactSelected: this.onShippingContactSelectedHandle.bind(this),
					onShippingMethodSelected: this.onShippingMethodSelectedHandle.bind(this),
					onAuthorized: this.onAuthorizedHandle.bind(this),
					onSubmit: this.onSubmitHandle.bind(this),
					onCancel: this.handleOnCancel.bind(this),
					onError: this.handleOnError.bind(this),
				});
				checkoutApple.isAvailable()
					.then(() => {
						checkoutApple.mount(this.mountSelector);
					})
					.catch(e => {
						console.log("no apple");
					});
			}

			async onClickHandle(resolve, reject) {
				this.disableAppleButton();
				resolve();
			}

		}
		global.LcAdyenApplePayExpress = LcAdyenApplePayExpress;
		document.dispatchEvent(new CustomEvent('LcAdyenApplePayExpressReady'));
	}

	if (typeof LcAdyenApplePayBaseExpress !== 'undefined') {
        init();
    } else {
        document.addEventListener('LcAdyenApplePayBaseExpressReady', init, { once: true });
    }
})(window);