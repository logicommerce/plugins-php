var lCGAnalytics4 = {};

if (lCGAFilterCode == undefined) {
    lCGAFilterCode = "sku";
}

window.addEventListener('load', function() {
    loadGA4();
});

const loadGA4 = () => {
    if (isObjectEmpty(lCGAnalytics4)) {
        lCGAnalytics4 = new LCGAnalytics4();
        lCGAnalytics4.setFilterCode(lCGAFilterCode);
        lCGAnalytics4.sendEventsGA4();
    }
}

LC.resources.addPluginListener('onRemoveProduct', function(event, data) {
    try {
        if (isObjectEmpty(lCGAnalytics4)) { loadGA4(); }
        let basketProduct = lCGAnalytics4.findProductBasket(data.hash);
        let dataEvent = lCGAnalytics4.getItemCartData(basketProduct, data.quantity);
        if (dataEvent != null) {
            gtag('event', 'remove_from_cart', dataEvent);
        }
    } catch (error) {
        console.log(error);        
    }
});

LC.resources.addPluginListener('onAddProduct', function(event, data) {
    try {
        if (isObjectEmpty(lCGAnalytics4)) { loadGA4(); }
        let dataEvent = {};
        if (data.hash) {
            let basketProduct = lCGAnalytics4.findProductBasket(data.hash);
            dataEvent = lCGAnalytics4.getItemCartData(basketProduct, data.quantity);
        } else {
            let product = lCGAnalytics4.findProduct(data.id);
            let options = lCGAnalytics4.getOptionValues(data.options);
            dataEvent = lCGAnalytics4.getAddToCartData(product, options, data);
        }
        if (dataEvent != null) {
            gtag('event', 'add_to_cart', dataEvent);
        }
    } catch (error) {
        console.log(error);        
    }
});

LC.resources.addPluginListener('setPaymentSystem', function(event, data) {
    try {
        if (isObjectEmpty(lCGAnalytics4)) { loadGA4(); }
        let payment = JSON.parse(data);
        let paymentName = lCGAnalytics4.findPayment("paymentSystem_"+payment.id);
        let dataEvent = lCGAnalytics4.getDataPayment(paymentName);
        gtag('event', 'add_payment_info', dataEvent);
    } catch (error) {
        console.log(error);        
    }
});

LC.resources.addPluginListener('setShippingSection', function(event, data) {
    try {
        if (isObjectEmpty(lCGAnalytics4)) { loadGA4(); }
        let shipperName = lCGAnalytics4.getShipperName(data.id);
        let dataEvent = lCGAnalytics4.getDataShipper(shipperName);
        gtag('event', 'add_shipping_info', dataEvent);
    } catch (error) {
        console.log(error);        
    }
});

LC.resources.addPluginListener('onUserLogin', function(data) {
    gtag('event', 'login');
});

LC.resources.addPluginListener('onAddWishList', function(event, data) {
    try {
        if (isObjectEmpty(lCGAnalytics4)) { loadGA4(); }
        let product = lCGAnalytics4.findProduct(data.id);
        let dataEvent = lCGAnalytics4.getProductViewData(product);
        if (dataEvent != null) {
            gtag('event', 'add_to_wishlist', dataEvent);
        }
    } catch (error) {
        console.log(error);
    }
});

LC.resources.addPluginListener('onAddShoppingList', function(event, data) {
    try {
        if (isObjectEmpty(lCGAnalytics4)) { loadGA4(); }
        let product = lCGAnalytics4.findProduct(data.id);
        let dataEvent = lCGAnalytics4.getProductViewData(product);
        if (dataEvent != null) {
            gtag('event', 'add_to_wishlist', dataEvent);
        }
    } catch (error) {
        console.log(error);
    }
});

LC.resources.addPluginListener('onUserSignUp', function(data) {
    if (isObjectEmpty(lCGAnalytics4)) { loadGA4(); }
    gtag('event', 'sign_up');
    
});

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {
    try {
        if (isObjectEmpty(lCGAnalytics4)) { loadGA4(); }
        if (oneStepCheckout) {
            form = data.el.$form;
        }
        let createAccount = form.find('#createAccount').val();
        if (createAccount == 1) {
            gtag('event', 'sign_up');
        }
    } catch (error) {
        console.log(error);        
    }
    return;
});

const isObjectEmpty = (objectName) => {
    return (
      objectName &&
      Object.keys(objectName).length === 0 &&
      objectName.constructor === Object
    );
}