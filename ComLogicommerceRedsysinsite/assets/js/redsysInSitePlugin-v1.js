LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
    const lCRedsysInPage = new LcRedsysInSite();
	lCRedsysInPage.REDSYS_INSITE_PAYMENT_MODULE = 'com.logicommerce.redsysinsite';
	lCRedsysInPage.setFormStep(oneStepCheckout, data);
	lCRedsysInPage.disablePayButton();
	if (!lCRedsysInPage.checkBeforeSubmit()) {
		return false;
	}
	ev.preventDefault();
	lCRedsysInPage.pcCheckoutForm.preventSubmit = true;
    let additonalData = { osc: oneStepCheckout };
    if (oneStepCheckout === true) {
        additonalData = { updateBasketRows: data.updateBasketRows, action: data.submitButton.val(), osc: oneStepCheckout };
    } else {
        data.dataForm = lCRedsysInPage.fillDataForm(data);
    }
    lCRedsysInPage.loadWidget(lCRedsysInPage.pcPaymentSystemId, data, additonalData);
	return false;
}, true);

class LcRedsysInSite {
	REDSYS_INSITE_PAYMENT_MODULE = 'com.logicommerce.redsysinsite';
	pcButtonStep = '#paymentAndShippingBasketContinue';
	pcPaymentSystemId = 0;
	pcCheckoutForm = {};
	paymentSystemSelected = {};
	CHECKOUT_URL = LC.global.routePaths.CHECKOUT;

	setFormStep(oneStepCheckout, data) {
		this.pcCheckoutForm = data;
		if (oneStepCheckout == true) {
			this.pcButtonStep = '#basketEndOrder';
			this.pcCheckoutForm = data.el.$form;
			this.CHECKOUT_URL = LC.global.routePaths.CHECKOUT;
		} else {
			this.pcButtonStep = '#paymentAndShippingBasketContinue';
			this.CHECKOUT_URL = LC.global.routePaths.CHECKOUT_PAYMENT_AND_SHIPPING;
		}
		return this.pcButtonStep;
	}

	checkBeforeSubmit() {		
		if (typeof this.pcCheckoutForm.preventSubmit != "undefined" && this.pcCheckoutForm.preventSubmit) {
			this.enablePayButton();
			return false;
		}
		const selected = this.pcCheckoutForm.find('.basketSelectorPaymentInput:checked');
		if (selected.length == 0) {
			this.enablePayButton();
			return false;
		}
		this.paymentSystemSelected = selected.val() || 0;
		this.pcPaymentSystemId = JSON.parse(this.paymentSystemSelected).id;
		if (this.pcPaymentSystemId == 0) {
			this.enablePayButton();
			return false;
		}
		if (selected.attr("data-plugin-module") != this.REDSYS_INSITE_PAYMENT_MODULE) {
			this.enablePayButton();
			return false;
		}
		return true;
	}

	disablePayButton() {
		$(this.pcButtonStep).prop('disabled', true);
	}

	enablePayButton() {
		$(this.pcButtonStep).prop('disabled', false);
	}

    loadWidget(paymentSystemId, data, additonalData) {
        let source = document.getElementById("redsysInSite_" + paymentSystemId);
        const checkoutUrl = this.CHECKOUT_URL;
        const buttonStep = this.pcButtonStep;
		$('<span/>').appendTo($(document.body)).box({
			uid: 'modalRedsysInSite',
			triggerOnClick: false,
			showFooter: false,
			source: source,
            keepSrc: true,
            backdrop: 'static',
            showFooter: false,
            showHeader: true,
            showClose: true,
			size: 'medium',
			type: 'html',
            callback : this.processOrder(data, additonalData)
		});
		$('#modalRedsysInSite').on('hidden.bs.modal', function() {
			window.location.href = checkoutUrl;
		});
        $('#modalRedsysInSite').on('shown.bs.modal', function() {
            $('#redsysInSite_' + paymentSystemId).css("display", "block");
            $('#card-form').css("height", "340px");
            let element = '<img src="https://pagosonline.redsys.es/wp-content/uploads/2022/05/Logo-Redsys-1-300x108.png" height="30px" border="0" alt="Redsys">' +
                '<button id="redsysClose_' + paymentSystemId + '" type="button" class="btn-close" data-bs-dismiss="modal" aria-label="close"></button>';
            $('#modalRedsysInSite .modal-header').html(element);
            $(buttonStep).prop('disabled', true);
            $('#redsysInsiteLoading').css("display", "block");
        });
	}

	async processOrder(data, additonalData) {
		let formData = new FormData();
		formData.append('data', JSON.stringify($.extend(data.dataForm, additonalData)));
		await fetch(LC.global.routePaths.CHECKOUT_INTERNAL_NEXT_STEP, {
			method: 'post',
			body: formData
		});
		const responseEndOrder = await fetch(LC.global.routePaths.CHECKOUT_END_ORDER, {
			method: 'post',
			headers: { 'content-type': 'application/json' }
		});
		const responseData = await responseEndOrder.json();
        if (responseData.transactionId != null) {
            this.getRedsysForm(responseData);
        } else {
            window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
        }
	}

    getRedsysForm(response) {
        const transactionId = response.transactionId;
        const operationType = response.operationType;
        if (operationType == "token") {
            let formData = new FormData();
            formData.append('operationType', operationType);
            formData.append('redsysInSiteTransactionId', transactionId);
            formData = this.dataEMV3DS(formData);
            this.processPay(formData);
            return;
        }
        window.addEventListener("message", function receiveMessage(event) {       
            const merchantValidation = function() { return true; }
            storeIdOper(event, "token", "errorCode", merchantValidation);
            if (event.data.result3DSMethod) {
                return;
            }
            if (event?.data?.idOper) {
                let formData = new FormData();
                formData.append('operationType', operationType);
                formData.append('redsysInSiteIdOper', event.data.idOper);
                formData.append('errorCode', event.data.errorCode);
                formData.append('redsysInSiteTransactionId', transactionId);
                const lCRedsysInPage = new LcRedsysInSite();
                formData = lCRedsysInPage.dataEMV3DS(formData);
                lCRedsysInPage.processPay(formData);
            }
        });
        this.createForm(response);
    }

    processPay(formData) {
        fetch(LC.global.routePaths.CHECKOUT_ASYNC_ORDER, {
            method: 'post',
            body: formData
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            if (data.dsAcsURL != null && data.dsCreq != null) {
                const params = {
                    'creq': data.dsCreq
                }
                const rdsPost = new RedsysInSitePost();
                rdsPost.post(data.dsAcsURL, params, "post");
            } else if (data.dsResponse == "0000") {
                const params = {
                    'dsResponse': data.dsResponse,
                    'dsAuthorisationCode': data.dsAuthorisationCode,
                    'Ds_MerchantParameters': data.dsMerchantParameters,
                    'Ds_Signature': data.dsSignature,
                    'Ds_SignatureVersion': data.dsSignatureVersion,
                };
                const rdsPost = new RedsysInSitePost();
                rdsPost.post(data.urlRedirection, params, "post");
            } else {
                window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
            }
        });
    }

    createForm(response) {
        const order = response.transactionId.toString();
        const merchantCode = response.merchantCode.toString();
        const terminal = response.terminal.toString();
        const language = this.getLanguage();
        const buttonLabel = LC.global.languageSheet.ComLogicommerceRedsysinsitePayButton;
        const widgetStyle = response?.widgetStyle || 'twoRows';
        const reducedStyle = response?.reduced || false;
        getInSiteForm('card-form', '', '', '', '', buttonLabel, merchantCode, terminal, 
            order, language, true, reducedStyle, widgetStyle, false);
        $('#redsysInsiteLoading').css("display", "none");
    }

    activeButtoOnClose(buttonStep, paymentSystemId) {
		document.getElementById("paypalClose_" + paymentSystemId).addEventListener("click", function() {
			document.getElementById(buttonStep).disabled = false;
		});
		document.addEventListener("keyup", ({ key }) => {
			if (key === "Escape") {
				document.getElementById(buttonStep).disabled = false;
			}
		});
	}

    fillDataForm(form) {
		let arrDataForm = form.serializeArray();
		let dataForm = {};
		for (const element of arrDataForm) {
			if (!(element.name in dataForm)) {
				dataForm[element.name] = [];
			}
			dataForm[element.name].push(element.value);
		}
		for (let i in this.dataForm) {
			dataForm[i] = dataForm[i].join();
		}
		return dataForm;
	}

    getLanguage() {
        if (lcCommerceData?.navigation?.language) {
            let language = lcCommerceData.navigation.language;
            return language.toUpperCase();
        } else {
            return 'ES';
        }
    }

    dataEMV3DS(formData) {
        formData.append('browserJavascriptEnabled', true);
        formData.append('browserJavaEnabled', navigator.javaEnabled());
        formData.append('browserLanguage', navigator.language || navigator.userLanguage);
        formData.append('browserColorDepth', screen.colorDepth);
        formData.append('browserScreenHeight', screen.height);
        formData.append('browserScreenWidth', screen.width);
        formData.append('browserTZ', new Date().getTimezoneOffset());
        formData.append('browserUserAgent', navigator.userAgent);
        formData.append('browserAcceptHeader', "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json");
        return formData;
    }
}

class RedsysInSitePost {
    post(path, params, method='post') {
	    const form = document.createElement('form');
        form.method = method;
        form.action = path;
        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                const hiddenField = document.createElement('input');
                hiddenField.type = 'hidden';
                hiddenField.name = key;
                hiddenField.value = params[key];
                form.appendChild(hiddenField);
            }
        }
        document.body.appendChild(form);
        form.submit();
    }
}