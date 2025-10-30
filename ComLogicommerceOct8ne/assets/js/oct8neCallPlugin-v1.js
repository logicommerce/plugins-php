LC.resources.addPluginListener('onAddProduct', function(event, data) {
    try {
        lcOct8ne.caller('cart');
    } catch (error) {
        console.log(error);  
    }
});

LC.resources.addPluginListener('onAddShoppingList', function(event, data) {
    try {
        lcOct8ne.caller('wishlist');
    } catch (error) {
        console.log(error);
    }
});

LC.resources.addPluginListener('onUserLogin', function(data) {
    try {
        lcOct8ne.caller('user');
    } catch (error) {
        console.log(error);
    }
});