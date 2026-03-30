(function (global) {

	function init() {

		class LcAdyenApplePayBaseExpress extends LcAdyenBaseV6 {
			static _lcPatchInstalled = false;
			static _checkoutInstance = null;
			static _paymentMethodsCache = null;
			static _paymentMethodsCacheTime = null;
			static _cacheTTL = 5 * 60 * 1000;

			constructor(settings) {
				super();
				this.configuration = {};
				this.tmpTotal = settings.total;
				this.pluginId = settings.pluginId;
				this.mountSelector = settings.selector;
				this.paymentSystemSelected = settings.paymentSystemId;
				this.paymentSystemId = settings.paymentSystemId;
				this.clientKey = settings.clientKey;
				this.environment = settings.environment;
				this.currencyCode = settings.currency;
				this.countryCode = settings.country;
				this.selectedShippingMethod = null;
				this.serverTotals = null;
				this.serverTaxes = null;
				this.module = "com.logicommerce.adyenv6";
				this.currenciesNoDecimal = [ 'JPY', 'CVE', 'DJF', 'GNF', 'IDR', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' ];
				this.currenciesThreeDecimal = [ 'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' ];
			}

			async getOrCreateCheckout(configuration) {
				if (LcAdyenApplePayBaseExpress._checkoutInstance) {
					return LcAdyenApplePayBaseExpress._checkoutInstance;
				}
				LcAdyenApplePayBaseExpress._checkoutInstance = await AdyenWeb.AdyenCheckout(configuration);
				return LcAdyenApplePayBaseExpress._checkoutInstance;
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

			async getPaymentMethods() {
				const now = Date.now();
				const cacheValid = LcAdyenApplePayBaseExpress._paymentMethodsCache &&
					LcAdyenApplePayBaseExpress._paymentMethodsCacheTime &&
					(now - LcAdyenApplePayBaseExpress._paymentMethodsCacheTime) < LcAdyenApplePayBaseExpress._cacheTTL;
				if (cacheValid) {
					return LcAdyenApplePayBaseExpress._paymentMethodsCache;
				}
				const payload = '{"id": ' + this.pluginId + ', "event": "SELECT_PAYMENT_SYSTEM", "data": "eventDataBasket"}';
				LcAdyenApplePayBaseExpress._paymentMethodsCache = this.getEventData(payload, "getPaymentMethods")
					.catch(err => {
						LcAdyenApplePayBaseExpress._paymentMethodsCache = null;
						LcAdyenApplePayBaseExpress._paymentMethodsCacheTime = null;
						throw err;
					});
				LcAdyenApplePayBaseExpress._paymentMethodsCacheTime = now;
				return LcAdyenApplePayBaseExpress._paymentMethodsCache;
			}

			async getEventData(data, action) {
				let formData = new FormData();
				formData.append('data', data);
				return fetch(LC.global.routePaths.RESOURCES_INTERNAL_PLUGIN_EXECUTE, {
					method: 'post',
					body: formData
				}).then(function(res) {
					return res.json();
				}).then(function(data) {
					let result = data.data.data.getPaymentMethods.data.paymentMethods;
					let paymentMethods = JSON.parse(result);
					return paymentMethods.paymentMethodsJson;
				});
			}

			disableAppleButton() {
				const container = document.querySelector(this.mountSelector);
				if (!container) return;
				const overlay = document.createElement('div');
				overlay.id = 'applePayOverlay_' + this.paymentSystemId;
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
				const overlay = document.getElementById('applePayOverlay_' + this.paymentSystemId);
				overlay?.remove();
			}

			handleOnCancel(data) {
				this.enableAppleButton();
			}

			handleOnError(error) {
				if (error.name === 'CANCEL' || error.statusCode === 'CANCELLED') {
					this.enableAppleButton();
				} else {
					this.errorValidation(LC.global.languageSheet.ComLogicommerceAdyenv6ErrorApplePay);
					this.enableAppleButton();
				}
			}

			async onPaymentMethodSelectedHandle(resolve, reject, event) {
				const totals = this.serverTotals;
				const taxes = this.serverTaxes;
				const method = this.selectedShippingMethod;
				const lineItems = this.getLineItems(totals, taxes, method);
				return resolve({
					newTotal: { label: LC.global.languageSheet.ComLogicommerceAdyenV6ExTotal, amount: this.parseAmount(totals.total), type: "final" },
					newLineItems: lineItems
				});
			}

			async onShippingContactSelectedHandle(resolve, reject, event) {
				this.fetchShippingMethods().then(async (result) => {
					const { methods: newShippingMethods, totals, taxes, items } = result;
					if (newShippingMethods.length === 0) {
						reject();
						return;
					}
					const method = newShippingMethods[0];
					this.selectedShippingMethod = method;
					this.serverTotals = totals;
					this.serverTaxes = taxes;
					const newTotal = {
						label: LC.global.languageSheet.ComLogicommerceAdyenV6ExTotal,
						amount: this.parseAmount(totals.total),
						type: "final"
					};
					const lineItems = this.getLineItems(totals, taxes, method);
					const response = {
						newTotal: newTotal,
						newLineItems: lineItems,
						newShippingMethods: newShippingMethods
					};
					const invalidItems = lineItems.filter(item => !item.amount || item.amount === 'NaN' || item.amount === 'undefined');
					if (invalidItems.length > 0) {
						reject();
						return;
					}
					resolve(response);
				}).catch(err => {
					reject();
				});
			}

			async fetchShippingMethods() {
				return fetch(LC.global.routePaths.BASKET_INTERNAL_GET_DELIVERY, {method: "post"})
					.then((res) => res.json())
					.then((data) => {
						const shippingMethods = data.data.data.items;
						return this.getShippingMethods(shippingMethods);
					}).catch((err) => {
						return { methods: [], totals: null, taxes: null };
					});
			}

			getShippingMethods(shippingMethods) {
				return new Promise((resolve, reject) => {
					let newShippingMethods = [];
					shippingMethods.filter(item => item.type === "SHIPPING").forEach(item => {
						item.shipments.forEach(shipment => {
							shipment.shippings.forEach(shipping => {
								const shipMethodObj = this.createShippingMethodObject(shipping, shipment, item);
								newShippingMethods.push(shipMethodObj);
							});
						});
					});
					let firstMethod = newShippingMethods[0];
					this.setBasketDelivery(firstMethod, resolve, reject).then(response => {
						resolve({
							methods: newShippingMethods,
							totals: response.data.data.totals,
							taxes: response.data.data.taxes,
							items: response.data.data.items
						});
					}).catch(error => {
						reject(error);
					});
				});
			}

			createShippingMethodObject(shipping, shipment, item) {
				const priceWithoutTax = Number(shipping.prices?.price || 0);
				const priceWithTax = Number(shipping.pricesWithTaxes?.price || 0);
				let totalDiscount = 0;
				if (shipping.appliedDiscounts && shipping.appliedDiscounts.length > 0) {
					totalDiscount = shipping.appliedDiscounts.reduce((sum, discount) => {
						return sum + Number(discount.discountValue || 0);
					}, 0);
				}
				let taxAmount = 0;
				let taxRate = 0;
				if (shipping.appliedTaxes && shipping.appliedTaxes.length > 0) {
					const firstTax = shipping.appliedTaxes[0];
					taxAmount = Number(firstTax.taxValue || 0);
					taxRate = Number(firstTax.tax?.taxRate || 0);
				} else {
					taxAmount = priceWithTax - priceWithoutTax;
				}
				const methodObj = {
					label: shipping.type?.shipper?.language?.name || "Envío",
					detail: shipping.type?.name || "",
					amount: this.parseAmount(priceWithoutTax),
					amountWithTax: this.parseAmount(priceWithTax),
					tax: this.parseAmount(taxAmount),
					taxRate: taxRate,
					discount: this.parseAmount(totalDiscount),
					identifier: this.getShippingIdentifier(shipment.hash, shipping.hash, item.hash)
				};
				return methodObj;
			}

			getShippingIdentifier(shipmentHash, shippingHash, deliveryHash) {
				return `${deliveryHash}###${shipmentHash}###${shippingHash}`;
			}

			async setBasketDelivery(shippingMethod, resolve, reject) {
				let identifier = shippingMethod.identifier.split("###");
				let dataDelivery = {
					type: 'SHIPPING',
					deliveryHash: identifier[0] || '',
					shipments: [],
				};
				dataDelivery.shipments.push({
					shipmentHash: identifier[1] || '',
					shippingHash: identifier[2] || ''
				});
				return await fetch(LC.global.routePaths.BASKET_INTERNAL_SET_DELIVERY, {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: new URLSearchParams({data: JSON.stringify(dataDelivery)})
				})
				.then(res => res.json())
				.then(response => {
					return response;
				})
				.catch(error => {
					reject(error);
				});
			}

			getLineItems(totals, taxes, method) {
				const lineItems = [];
				lineItems.push({ 
					label: LC.global.languageSheet.ComLogicommerceAdyenV6Subtotal, 
					amount: this.parseAmount(totals.subtotalRows),
					type: "final"
				});
				lineItems.push({ 
					label: method.label + " - " + method.detail, 
					amount: this.parseAmount(totals.subtotalShippings),
					type: "final"
				});
				taxes.forEach(tax => {
					if (tax?.discount > 0) {
						lineItems.push({ 
							label: LC.global.languageSheet.ComLogicommerceAdyenV6ExDiscounts,
							amount: "-" + this.parseAmount(tax.discount),
							type: "final"
						});
					}
					let subTotal = tax?.base || 0;
					lineItems.push({ 
						label: LC.global.languageSheet.ComLogicommerceAdyenV6ExTaxBase, 
						amount: this.parseAmount(subTotal),
						type: "final"
					});
					lineItems.push({ 
						label: LC.global.languageSheet.ComLogicommerceAdyenV6ExTax + ' ' + (tax?.taxRate || 0).toFixed(0) + "%", 
						amount: (tax?.taxValue || 0).toFixed(0),
						type: "final"
					});
				});
				return lineItems;
			}

			async onShippingMethodSelectedHandle(resolve, reject, event) {
				const method = event.shippingMethod;
				this.selectedShippingMethod = method;
				let newTotals = null;
				let newTaxes = null;
				await this.setBasketDelivery(method, resolve, reject)
					.then(response => {
						newTotals = response.data.data.totals;
						newTaxes = response.data.data.taxes;
					}).catch(error => {
						reject(error);
					});
				const totalAmount = newTotals ? newTotals.total : this.serverTotals.total / 100;
				const lineItems = this.getLineItems(newTotals, newTaxes, method);
				resolve({
					newTotal: { 
						label: LC.global.languageSheet.ComLogicommerceAdyenV6ExTotal, 
						amount: this.parseAmount(totalAmount),
						type: "final"
					},
					newLineItems: lineItems
				});
			}

			async onAuthorizedHandle(data, actions) {
				let billingContact = data.authorizedEvent.payment.billingContact || {};
				let shippingContact = data.authorizedEvent.payment.shippingContact || {};
				let userData = {
					adyenTransactionIdentifier: data.authorizedEvent.payment.token.transactionIdentifier,
					billingData: this.getBillingData(billingContact, shippingContact),
					shippingData: this.getShippingData(shippingContact),
					email: shippingContact.emailAddress || billingContact.emailAddress || "",
					name: shippingContact.givenName || billingContact.givenName || "",
					lastName: shippingContact.familyName || billingContact.familyName || ""
				};
				fetch(LC.global.routePaths.CHECKOUT_INTERNAL_EXPRESS_CHECKOUT_ACCOUNT, {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify(userData)
				}).then(res => res.json())
				.then(response => {
					actions.resolve(); 
				}).catch(error => {
					actions.reject();
				});
			}

			getBillingData(billingContact, shippingContact) {
				let billingData = {
					email: billingContact.emailAddress || shippingContact.emailAddress || "",
					phone: billingContact.phoneNumber || shippingContact.phoneNumber || "",
					name: billingContact.givenName || shippingContact.givenName || "",
					lastName: billingContact.familyName || shippingContact.familyName || "",
					address: this.getAddress(billingContact.addressLines) || this.getAddress(shippingContact.addressLines) || "",
					postalCode: billingContact.postalCode || shippingContact.postalCode || "",
					city: billingContact.locality || shippingContact.locality || "",
					state: billingContact.administrativeArea || shippingContact.administrativeArea || "",
					country: billingContact.countryCode || shippingContact.countryCode || ""
				};
				return JSON.stringify(billingData);
			}

			getAddress(addressLines) {
				return addressLines ? addressLines.join(", ") : "";
			}

			getShippingData(shippingContact) {
				let shippingData = {
					name: shippingContact.givenName || "",
					lastName: shippingContact.familyName || "",
					address: this.getAddress(shippingContact.addressLines) || "",
					postalCode: shippingContact.postalCode || "",
					city: shippingContact.locality || "",
					state: shippingContact.administrativeArea || "",
					country: shippingContact.countryCode || "",
					phone: shippingContact.phoneNumber || "",
				};
				return JSON.stringify(shippingData);
			}

			async onSubmitHandle(state, component, actions) { 
				if (state.data.paymentMethod && state.isValid) {
					const paymentData = state.data.paymentMethod;
					if (state.data.storePaymentMethod) {
						paymentData.storeDetails = state.data.storePaymentMethod;
					}
					let data = this.setAdyenData(paymentData);
					await this.processEndOrder(data);
					actions.resolve({ resultCode: 'Authorised' });
					LC.CheckoutForm.prototype.loadingWindow(this.buttonStep, LC.global.languageSheet.ComLogicommerceAdyenV6ProcessingPayment);
				} else {
					this.errorValidation(LC.global.languageSheet.completePaymentInformation);
					this.enableAppleButton();
					actions.reject();
				}
			}

			setAdyenData(paymentData) {
				let data = {id: "", additionalData: ""};
				data.id = this.paymentSystemSelected;
				data.additionalData = '{"module":"' + this.module + '","paymentData":' + JSON.stringify(paymentData) + '}';
				return data;
			}

			async processEndOrder(data) {
				try {
					const paymentResponse = await this.setPaymentSystem(data);
					if (!paymentResponse.data.response.success) {
						window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
						return;
					}
					return await this.runEndOrder();
				} catch (e) {
					window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
				}
			}

			async setPaymentSystem(data) {
				const res = await fetch(LC.global.routePaths.BASKET_INTERNAL_SET_PAYMENT_SYSTEM, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'data=' + encodeURIComponent(JSON.stringify(data))
				});
				return res.json();
			}

			getAdyenAmount(total) {
				if (this.currenciesNoDecimal.includes(this.currencyCode)) {
					return Math.round(total);
				} else if (this.currenciesThreeDecimal.includes(this.currencyCode)) {
					return Math.round(total * 1000);
				} else {
					return Math.round(total * 100);
				}
			}

			parseAmount(total) {
				if (this.currenciesNoDecimal.includes(this.currencyCode)) {
					return Math.round(total);
				} else if (this.currenciesThreeDecimal.includes(this.currencyCode)) {
					return total.toFixed(3);
				} else {
					return total.toFixed(2);
				}
			}
		}

		global.LcAdyenApplePayBaseExpress = LcAdyenApplePayBaseExpress;
		document.dispatchEvent(new CustomEvent('LcAdyenApplePayBaseExpressReady'));
	}

    if (typeof LcAdyenBaseV6 !== 'undefined') {
        init();
    } else {
        document.addEventListener('LcAdyenBaseReady', init, { once: true });
    }
})(window);