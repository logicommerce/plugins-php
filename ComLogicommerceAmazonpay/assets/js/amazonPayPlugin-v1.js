class LcAmazonPay {
    merchantId = "ViewContent";
    publicKeyId = {};
    lcCommerceData = {};
    payloadJSON = {};
    signature = {};
    pluginId = "0";
    productId = "0";

    renderPayButton(target) {
        var amazonPayButton = amazon.Pay.renderButton("#" + target, {
            merchantId: this.merchantId,
            publicKeyId: this.publicKeyId,
            ledgerCurrency: this.getCurrency(),
            checkoutLanguage: this.getLanguage(),
            productType: 'PayAndShip',
            placement: 'Cart',
            buttonColor: 'Gold'
        });
        this.setPayButtonEvent(amazonPayButton);
    }

    cancelAction() {
        amazon.Pay.signout();
    }

    addActionButton(pluginId) {
        let newButton = document.createElement('button');
        newButton.id = 'express-checkout-id-' + pluginId;
        newButton.style.display = 'none';
        document.body.appendChild(newButton);
        return newButton;
    }

    async changeAction(pluginId) {
        let payload = '{"id": ' + pluginId + ', "event": "SELECT_PAYMENT_SYSTEM", "data": "eventDataBasket"}';
        const datas = await this.getEventData(payload, "getAmzSession");
        let newButton = this.addActionButton(pluginId);
        amazon.Pay.bindChangeAction('#express-checkout-id-'+pluginId, {
            amazonCheckoutSessionId: await datas.sessionId,
            changeAction: 'changeAddress'
        });
        newButton.click();
    }

    async renderLoginButton(target) {
        let payload = '{"id": ' + this.pluginId + ', "event": "LOGIN_EVENT", "data": "{eventDataBasket}"}';
        const datas = await this.getEventData(payload, "loginAmz");
        let amzPayloadJSON = datas.payloadJSON;
        let amzSignature = datas.signature;
        amazon.Pay.renderButton("#" + target, {
            merchantId: this.merchantId,
            publicKeyId: this.publicKeyId,
            ledgerCurrency: this.getCurrency(),
            checkoutLanguage: this.getLanguage(),
            productType: 'SignIn',
            placement: 'Cart',
            buttonColor: 'Gold',
            signInConfig: {
                payloadJSON: amzPayloadJSON,
                signature: amzSignature,
                algorithm: 'AMZN-PAY-RSASSA-PSS-V2'
            }
        });
    }

    async setPayButtonEvent(amazonPayButton) {
        let payload = '{"id": ' + this.pluginId + ', "event": "SELECT_PAYMENT_SYSTEM", "data": "eventDataBasket"}';
        const datas = await this.getEventData(payload, "getAmzData");
        let amzProduct = this.productId;
        let amzPayloadJSON = datas.payloadJSON;
        let amzSignature = datas.signature;
        let amzPublicKey = this.publicKeyId;
        amazonPayButton.onClick(function() {
            if (amzProduct?.length > 0) {
                $("#buyFormSubmit" + amzProduct).submit();
            }
            amazonPayButton.initCheckout({
                createCheckoutSessionConfig: {
                    payloadJSON: amzPayloadJSON,
                    signature: amzSignature,
                    algorithm: 'AMZN-PAY-RSASSA-PSS-V2',
                    publicKeyId: amzPublicKey
                }
            });
        });
    }

    loadPayButtonCheckout(merchantId, publicKeyId, payload, signature) {
        $('body').append($("<div>", { "id":"amazonPayButtonCheckout"}));
        var amazonPayButton = amazon.Pay.renderButton('#amazonPayButtonCheckout', {
            merchantId: merchantId,
            publicKeyId: publicKeyId,
            ledgerCurrency: 'EUR',
            checkoutLanguage: this.getLanguage(),
            productType: 'PayAndShip',
            placement: 'Cart'
        });
        amazonPayButton.initCheckout({
            createCheckoutSessionConfig: {                     
                payloadJSON: payload, 
                signature: signature,
                publicKeyId: publicKeyId,
                algorithm: 'AMZN-PAY-RSASSA-PSS-V2'
            }   
        });
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
            let result = {};
            if (action == "getAmzData") {
                result = data.data.data.getAmzData.data;
            } else if (action == "loginAmz") {
                result = data.data.data.loginAmz.data;
            } else if (action == "getAmzSession") {
                result = data.data.data.getAmzSession.data;
                return { sessionId: result.sessionId };
            }
            let signature = result.signature;
            let payloadJSON = result.payload;
            return { payloadJSON: payloadJSON, signature:signature};
		});
	}

    setData(lcCommerceData) {
        this.lcCommerceData = lcCommerceData;
    }

    setPublicKey(publicKey) {
        this.publicKeyId = publicKey;
    }

    setMerchantId(merchantId) {
        this.merchantId = merchantId;
    }

    setPayloadJSON(payloadJSON) {
        this.payloadJSON = payloadJSON;
    }

    setSignature(signature) {
        this.signature = signature;
    }

    setPluginId(pluginId) {
        this.pluginId = pluginId;
    }

    setProductId(productId) {
        this.productId = productId;
    }

    getCurrency() {
        if (lcCommerceData?.navigation?.currency) {
            return lcCommerceData?.navigation?.currency;
        } else {
            return 'EUR';
        }
    }
    
    getLanguage() {
        if (lcCommerceData?.navigation?.language) {
            return this.parseLanguage(lcCommerceData?.navigation?.language);
        } else {
            return 'es_ES';
        }
    }

    parseLanguage(language) {
        switch (language) {
            case 'es':
                return 'es_ES';
            case 'ca':
                return 'ca_ES';
            case 'en':
                return 'en_US';
            case 'fr':
                return 'fr_FR';
            case 'de':
                return 'de_DE';
            case 'it':
                return 'it_IT';
            case 'pt':
                return 'pt_PT';
            default:
                return 'es_ES';
        }
    }
}