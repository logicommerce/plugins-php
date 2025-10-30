
class LCGAnalytics4 {

    filterCodeId = "id";

    setFilterCode(filterCodeId) {
        this.filterCodeId = "id";
        if (filterCodeId !== undefined) {
            this.filterCodeId = filterCodeId;
        }
    }

    sendEventsGA4() {
        let pageType = lcCommerceData.navigation.type;
        if (pageType == "CATEGORY") {
            this.sendViewItems();
        } else if (pageType == "PRODUCT") {
            this.sendViewItem();
        } else if (pageType == "SEARCH") {
            this.sendSearchViewItems();
        } else if (pageType == "CHECKOUT" || pageType == "CHECKOUT_BASKET") {
            this.sendCheckout();
        } else if (pageType == "CHECKOUT_CONFIRM_ORDER") {
            this.sendPurchase();
        }
    }

    sendViewItem() {
        let product = lcCommerceData.main_pageProduct;
        let dataEvent = this.getProductViewData(product);
        gtag('event', "view_item", dataEvent);
    }

    sendSearchViewItems() {
        const urlParams = new URLSearchParams(window.location.search);
        let criteria = urlParams.get('q');
        let products = lcCommerceData.main_pageProducts.products;
        let category = { name: "search", pId:"SEARCH"}
        let dataEvent = this.getItemListViewData(products, category);
        dataEvent.search_criteria = criteria;
        gtag('event', 'view_item_list', dataEvent);
    }

    sendViewItems() {
        let products = lcCommerceData.main_pageCategory.products;
        if (products) {
            let products = lcCommerceData.main_pageCategory.products;
            let category = lcCommerceData.main_pageCategory;
            let dataEvent = this.getItemListViewData(products, category);
            gtag('event', 'view_item_list', dataEvent);
        }
    }

    sendCheckout() {
        let dataEvent = this.getDataBasket();
        gtag('event', 'begin_checkout', dataEvent);
    }

    sendPurchase() {
        let dataEvent = this.getDataOrder();
        gtag('event', 'purchase', dataEvent);
    }

    getItemListViewData(products, category) {
        let productList = [];
        for (let i = 0; i < products.length; i++) {
            let product = products[i];
            let productInfo = this.getProductInfo(product);
            productList.push(productInfo);
        }
        let prouctListData = {
            items : productList,
            item_list_name: category.name,
            item_list_id: category.pId || category.id
        }
        return prouctListData;
    }

    getProductViewData(product) {        
        const item = this.getProductInfo(product);
        return this.getItemData(item);
    }

    getAddToCartData(product, options, data) {
        const item = this.getProductInfo(product, data.quantity);
        if (data.prices != undefined) {
            const basePrice = eval(data.prices.basePrice);
            const retailPrice = eval(data.prices.retailPrice);
            item.price = this.round(basePrice);
            item.discount = this.round(basePrice - retailPrice);
        }
        let variant = [];
        for (let i = 0; i < options.length; i++) {
            let value = this.findOptionName(options[i].optionId, options[i].valueId, product)
            value = this.getParsedValue(value);
            variant.push(value);
        }
        return this.getItemData(item, variant.join(), data.quantity);
    }

    getItemCartData(product, quantity) {
        const item = this.getProductBasket(product);
        if (quantity != undefined) {
            item.quantity = quantity;
        } else {
            item.quantity = product.quantity;
        }
        let variant = this.getBasketProductOption(product.options);
        return this.getItemData(item, variant.join());
    }

    getBasketProductOption(options) {
        let variant = [];
        for (let i = 0; i < options.length; i++) {
            let value = options[i].value.value;
            if (value != null) {
                value = this.getParsedValue(value);
                variant.push(value);
            }
        }
        return variant;
    }

    getOrderProductOption(options) {
        let variant = [];
        for (let i = 0; i < options.length; i++) {
            let value = options[i].value;
            if (value != null) {
                value = this.getParsedValue(value);
                variant.push(value);
            }
        }
        return variant;
    }

    getParsedValue(value) {
        return value.replace(/<(.|\n)*?>/g, '');
    }

    getItemData(item, variant, quantity) {
        const navigation = lcCommerceData.navigation;
        let data = {
            currency: navigation.currency,
            items: [item]
        };
        if (variant != undefined && variant.length) {
            item.item_variant = variant;
        }
        const price = item.price - item.discount;
        if (quantity != null) {
            data.value = this.round(price * quantity);
        } else {
            data.value = this.round(price);
        }
        return data;
    }

    findProduct(productId) {
        const mainCategory = lcCommerceData.main_pageCategory;
        if (mainCategory && mainCategory.products.length) {
            return mainCategory.products.find(product => product.id == productId);
        }
        const mainSearch = lcCommerceData.main_pageProducts;
        if (mainSearch && mainSearch.products.length) {
            return mainSearch.products.find(product => product.id == productId);
        }
        return lcCommerceData.main_pageProduct;
    }

    findProductBasket(hash) {
        let basket = lcCommerceSession.basket;
        let products = [];
        if (basket != undefined && basket.rows.length) {
            products = basket.rows;
            return products.find(product => product.hash == hash);
        } 
        return {};
    }

    getOptionValues(options) {
        let arrValues = [];
        for (options of options) {
            let values = {};
            values.optionId = options.id;
            values.valueId = options.values[0].value;
            arrValues.push(values);
        }
        return arrValues;
    }

    findOptionName(optionId, valueId, product) {
        const option = product.options.find(option => option.id == optionId);
        if (!option) {
            return "";
        }
        const value = option.values.find(value => value.id == valueId);
        if (!value) {
            return "";
        }
        return value.name;
    }

    getProductInfo(product, quantity = 1) {
        let productInfo = {
            item_id: this.getProductId(product),
            item_name: product.name,
            item_brand: product.brandName || product.brand,
            item_category: product.categoryName || product.category,
            currency: lcCommerceData.navigation.currency,
            quantity: quantity
        }
        if (product.offer) {
            if (product.pricesWithTaxes.price > 0) {
                productInfo.price = this.round(product.pricesWithTaxes.price);
                productInfo.discount = this.round(product.pricesWithTaxes.price - product.pricesWithTaxes.retailPrice);
            } else {
                productInfo.price = 0;
                productInfo.discount = 0;
            }
        } else {
            productInfo.price = this.round(product.pricesWithTaxes.retailPrice);
            productInfo.discount = 0;
        }
        return productInfo;
    }

    getProductBasket(product) {
        let productBasket = this.getProductBasicInfo(product, product.quantity);
        productBasket.price = this.round(product.pricesWithTaxes.price);
        productBasket.discount = this.round(product.pricesWithTaxes.totalDiscounts);
        let variant = this.getBasketProductOption(product.options);
        if (variant != undefined && variant.length) {
            productBasket.item_variant = variant.join();
        }
        return productBasket;
    }

    getProductOrder(product) {
        let productOrder = this.getProductBasicInfo(product, product.quantity);
        productOrder.price = this.round(product.prices.previousPriceWithTaxes);
        productOrder.discount = this.round(product.prices.totalWithDiscountsWithTaxes - product.prices.previousPriceWithTaxes);
        let variant = this.getOrderProductOption(product.options);
        if (variant != undefined && variant.length) {
            productOrder.item_variant = variant.join();
        }
        return productOrder;
    }

    getProductBasicInfo(product, quantity =1) {
        let productBasic = {
            item_id: this.getProductId(product),
            item_name: product.name,
            item_brand: product.brandName || product.brand || "",
            item_category: product.categoryName || product.category || "",
            currency: lcCommerceData.navigation.currency,
            quantity: quantity
        }
        return productBasic;
    }

    getCode(codes) {
        if (codes.sku) {
            return codes.sku;
        } else if (codes.manufactureSku) {
            return codes.manufactureSku;
        }
    }

    getDataBasket() {
        let basket = lcCommerceSession.basket;
        let items = [];
        if (basket != undefined && basket.rows.length) {
            items = this.getProductsItems(basket.rows, false);
        }
        let data = {
            items: items,
            currency: basket.currency,
            value: this.round(basket.totals.total)
        }
        return data;
    }

    getDataOrder() {
        let navigation = lcCommerceData.navigation;
        let order = lcCommerceData.order;
        let items
        if (order != undefined && order.rows.length) {
            items = this.getProductsItems(order.rows, true);
        }
        let data = {
            items: items,
            currency: navigation.currency,
            value: this.round(order.totals.total),
            tax: this.round(order.totals.totalTaxes),
            shipping: this.round(order.totals.totalShipping),
            transaction_id: order.documentNumber,
            order_id: order.id
        }
        return data;
    }

    getDataPurchase(name) {
        let data = this.getDataBasket();
        data.shipping = "";
        data.tax = "";
        data.transaction_id = "name";
        return data;
    }

    getDataPayment(name) {
        let data = this.getDataBasket();
        data.payment_type = name;
        return data;
    }

    getDataShipper(name) {
        let data = this.getDataBasket();
        data.shipping_tier = name;
        return data;
    }

    getProductsItems(items, order) {
        let products = [];
        if (items != undefined) {
            for (let i = 0; i < items.length; i++) {
                let product = items[i];
                let productInfo = {};
                if (order) {
                    productInfo = this.getProductOrder(product);
                } else {
                    productInfo = this.getProductBasket(product);
                }
                products.push(productInfo);
            }
        }
        return products;
    }

    getIdentifier(element) {
        var parts = element.split("_");
        var identifier = {};
        if (parts.length >= 3) {
            identifier.productId = parts[1];
            let options = [];
            for (let i = 2; i < parts.length; i++) {
                options.push(parts[i]);
            }
            identifier.options = options;
        } else if (parts.length == 2) {
            identifier.productId = parts[1];
            identifier.options = [];
        } else {
            identifier.productId = 0;
            identifier.options = [];
        }
        return identifier;
    }

    findPayment(payment) {
        var paymentName =  document.querySelector("label[for='"+payment+"']").textContent;        
        if (paymentName != undefined) {
            return paymentName;
        }
        return payment;
    }

    getShipperName(shipping) {
        var shipperElement = document.querySelector('[data-lc-shipping-hash="' + shipping + '"]');        
        if (shipperElement) {
            var container = shipperElement.parentElement;
            var shipperNameElement = container.querySelector('.shipperName');            
            if (shipperNameElement) {
                return shipperNameElement.textContent.trim();
            }
        }        
        return shipping;
    }

    getProductId(product) {
        let id = product.id || product.item_id;
        id = id.toString();
        let codes = product.codes;
        switch (this.filterCodeId) {
            case "id" : return id;
            case "pid" : return this.checkValue(product.pid, id);
            case "sku" : return this.checkValue(codes.sku, id);
            case "ean" : return this.checkValue(codes.ean, id);
            case "jan" : return this.checkValue(codes.jan, id);
            case "upc" : return this.checkValue(codes.upc, id);
            case "manufacturer_sku" : return this.checkValue(codes.manufacturerSku, id);
            case "isbn" : return this.checkValue(codes.isbn, id);
            default : return id;
        }
    }

    checkValue(value, defaultValue) {
        return value || defaultValue;
    }

    round(num) {
        return Math.round((num + Number.EPSILON) * 100) / 100;
    }
}