
class LcOct8ne {
    platform = "LogiCommerce";
    server = "";
    type = "text/javascript";
    async = true;
    license = "";
    staticUrl = "";
    src = "";
    locale = "";
    baseUrl = "";
    apiVersion = "2.4";
    checkoutUrl = "";
    checkoutSuccessUrl = "";
    currencyCode = "";
    lcCommerceData = {};
    script = {};

    create() {
        oct8ne.platform = this.platform;
        oct8ne.server = this.server;
        oct8ne.type = this.type;
        oct8ne.async = this.async;
        oct8ne.license = this.license;
        oct8ne.src = this.getOct8neSrc();
        oct8ne.locale = this.getLanguage();
        oct8ne.baseUrl = this.baseUrl;
        oct8ne.apiVersion = this.apiVersion;
        oct8ne.checkoutUrl = this.checkoutUrl;
        oct8ne.checkoutSuccessUrl = this.checkoutSuccessUrl;
        oct8ne.currencyCode = this.currencyCode;
        this.script = document.getElementsByTagName("script")[0];
        if (this.getPageType() == "PRODUCT") {
            this.addCurrentProduct();
        }
        this.insertOct8ne(oct8ne);
    }

    addCurrentProduct() {
        let product = this.lcCommerceData.main_pageProduct;
        oct8ne.currentProduct = {
            id: product.id,
            thumbnail: product.image
        };
    }

    getOct8neSrc() {
        let protocol = (document.location.protocol == "https:" ? "https://" : "http://");
        let script = this.staticUrl + "/api/v2/oct8ne.js?";
        let time = Math.round(new Date().getTime() / 86400000);
        return protocol + script + time;
    }

    insertOct8ne(oct8ne) {
        this.script.parentNode.insertBefore(oct8ne, this.script);
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
                return 'es-ES';
            case 'ca':
                return 'ca-ES';
            case 'en':
                return 'en-US';
            case 'fr':
                return 'fr-FR';
            case 'de':
                return 'de-DE';
            case 'it':
                return 'it-IT';
            case 'pt':
                return 'pt-PT';
            case 'ru':
                return 'ru-RU';
            default:
                return 'es-ES';
        }
    }

    async caller(what) {
        const visitor = this.getCookie('oct8ne-visitor');
        var url = 'http://' + this.server + 'platformConnection/update?visitor=' + visitor + '&what=' + what;
        fetch(url, {
            method: 'POST',
            mode: 'no-cors', 
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
    }

    getCookie(name) {
        let nameEQ = name + "=";
        let ca = document.cookie.split(';');
        for (var i=0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0)==' ') {
                c = c.substring(1,c.length);
            }
            if (c.indexOf(nameEQ) == 0) {
                return c.substring(nameEQ.length,c.length);
            }
        }
        return null;
    }

    getPageType() {
        return this.lcCommerceData.navigation.type;
    }

    setData(lcCommerceData) {
        this.lcCommerceData = lcCommerceData;
    }

    setSession(lcCommerceSession) {
        this.lcCommerceSession = lcCommerceSession;
    }

    setLicence(license) {
        this.license = license;
    }

    setServer(server) {
        this.server = server;
    }

    setStaticUrl(staticUrl) {
        this.staticUrl = staticUrl;
    }

    setBaseUrl(baseUrl) {
        this.baseUrl = baseUrl;
    }

    setPlatform(platform) {
        this.platform = platform;
    }

    setApiVersion(apiVersion) {
        this.apiVersion = apiVersion;
    }

    setCheckoutUrl(checkoutUrl) {
        this.checkoutUrl = checkoutUrl;
    }

    setCheckoutSuccessUrl(checkoutSuccessUrl) {
        this.checkoutSuccessUrl = checkoutSuccessUrl;
    }

    setCurrencyCode(currencyCode) {
        this.currencyCode = currencyCode;
    }
}