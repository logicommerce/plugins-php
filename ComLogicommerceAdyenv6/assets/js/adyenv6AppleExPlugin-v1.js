(function (global) {

	function initAPEx() {

		class LcAdyenApplePayExpress extends LcAdyenApplePayBaseExpress {
			static _ctxKey = "__LC_APPLEPAY_CTX_ONE";

			constructor(settings) {
				super(settings);
				this.productId = settings.productId;
			}

			static installLcSubmitPatch() {
				if (LcAdyenApplePayExpress._lcPatchInstalled) {
					return;
				}
				const BP = window.LC?.BuyProductForm;
				if (!BP?.prototype?.submit) {
					return;
				}
				const originalSubmit = BP.prototype.submit;
				BP.prototype.submit = function (...args) {
					const ctx = window[LcAdyenApplePayExpress._ctxKey];
					if (ctx) {
						const originalInstanceCb = this.callback;
						this.callback = function (data, status, event) {
							this.callback = originalInstanceCb;
							window[LcAdyenApplePayExpress._ctxKey] = null;
							ctx.instance.handleLcBuyProductCallback(ctx, data, status, event);
						};
						setTimeout(() => {
							if (window[LcAdyenApplePayExpress._ctxKey] === ctx) {
								window[LcAdyenApplePayExpress._ctxKey] = null;
							}
						}, 2000);
					}
					return originalSubmit.apply(this, args);
				};
				LcAdyenApplePayExpress._lcPatchInstalled = true;
			}

			handleLcBuyProductCallback(ctx, data, status, event) {
				this.serverTotals = data.data.data.totals;
				this.serverTaxes = data.data.data.taxes || [];
				ctx.instance.tmpTotal = Math.round(Number(this.serverTotals.total) * 100);
				ctx.resolve();
			}

			async render() {
				LcAdyenApplePayExpress.installLcSubmitPatch();
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

			disableAppleButton() {
				const container = document.querySelector(this.mountSelector);
				if (!container) return;
				const overlay = document.createElement('div');
				overlay.id = 'applePayOverlay_' + this.paymentSystemId + '_' + this.productId;
				Object.assign(overlay.style, {
					position: 'absolute',
					top: '0', left: '0',
					width: '100%', height: '100%',
					zIndex: '1000',
					cursor: 'normal',
					opacity: '0.65',
				});
				container.style.position = 'relative';
				container.appendChild(overlay);
			}

			enableAppleButton() {
				const overlay = document.getElementById('applePayOverlay_' + this.paymentSystemId + '_' + this.productId);
				overlay?.remove();
			}

			async onClickHandle(resolve, reject) {
				if (this._processing) {
					return reject();
				}
				this._processing = true;
				try {
					this.disableAppleButton();
					window[LcAdyenApplePayExpress._ctxKey] = { resolve, reject, instance: this };
					fetch(LC.global.routePaths.CHECKOUT_INTERNAL_CLEAR_BASKET, { method: 'post', body: "" }).then(() => {
						$("#buyFormSubmit" + this.productId).trigger("submit");
					});
				} catch (e) {
					reject();
				} finally {
					this._processing = false;
				}
			}
		}
	
		global.LcAdyenApplePayExpress = LcAdyenApplePayExpress;
		document.dispatchEvent(new CustomEvent('LcAdyenApplePayExpressReady'));
	}

	if (typeof LcAdyenApplePayBaseExpress !== 'undefined') {
        initAPEx();
    } else {
        document.addEventListener('LcAdyenApplePayBaseExpressReady', initAPEx, { once: true });
    }

})(window);