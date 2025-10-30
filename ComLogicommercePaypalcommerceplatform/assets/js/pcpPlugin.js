var PCP_PAYMENT_MODULE = 'com.logicommerce.paypalcommerceplatform';
var PCP_PAYPAL = "pcpPaypal";
var PCP_CARD = "pcpCard";
var W_LEFT = (screen.width/2) - 250;
var W_TOP = (screen.height/2) - 375;
var TARGET_ELEMENT = "paypal-button-container";
var paypalTransactionId = null;

LC.resources.addPluginListener('beforeSubmitEndOrder', function(ev, data, oneStepCheckout) {  	
  var checkoutForm = data;
	if (oneStepCheckout) {
		checkoutForm = data.el.$form;
		buttonStep = '#basketEndOrder';
	} else {
		buttonStep = '#paymentAndShippingBasketContinue';
	}
  if (typeof checkoutForm.preventSubmit != "undefined" && checkoutForm.preventSubmit) {
    return false;
  }
  if (checkoutForm.find('.basketSelectorPaymentInput:checked').length == 0) {
    return false;
  }
  var selected = checkoutForm.find('.basketSelectorPaymentInput:checked');
	var paymentSystemSelected = selected.val() || 0;
  var paymentSystemId = JSON.parse(paymentSystemSelected).id;
  var PCPElementId = '#paypal-checkout-platform-' + paymentSystemId;

  if (selected.attr("data-plugin-module") == PCP_PAYMENT_MODULE) {
    ev.preventDefault();
    checkoutForm.preventSubmit = true;
    var paymentType = $(PCPElementId).attr("data-method") || "";
    if (paymentType == PCP_PAYPAL) {
      runPayPal();
    } else if (paymentType == PCP_CARD) {
      runAdvancedCards(paymentSystemId);
    } else {
      redirectToDeniedOrder();
    }
  }
}, true);

var runPayPal = function() {
  document.getElementById(TARGET_ELEMENT).style.display = "block";
  document.body.style.overflow = 'hidden';
  $.post(LC.global.routePaths.CHECKOUT_END_ORDER, {}, function(response) {
    var responsePaypal = JSON.parse(response);
    if (responsePaypal.status == "CREATED" && responsePaypal.links.length) {
      var links = responsePaypal.links.filter(function(el){
        return el.rel == "approve"
      });
      if (links.length) {
        var approveUrl = links[0].href;  
        window.location.href = approveUrl;
      } else {
        redirectToDeniedOrder();  
      }
    } else {
      redirectToDeniedOrder();
    }
  });
};

var runAdvancedCards = function(paymentSystemId) {
  var loadScript = function(response) {
    $('#paypal-layout-'+paymentSystemId).remove();
    script = document.createElement('script');
    script.type = 'text/javascript';
    script.async = true;
    script.onload = function(){
      if (typeof paypal !== 'undefined') {
        loadPaymentForm(paymentSystemId);
      } else {
        redirectToDeniedOrder();
      }
    }
    var clientToken = getConfigAttr(paymentSystemId, "client-token");
    var clientSrc = getConfigAttr(paymentSystemId, "client-src");
    var bnCode = getConfigAttr(paymentSystemId, "bncode");
    script.src = clientSrc;
    script.setAttribute('data-client-token', clientToken);
    script.setAttribute('data-partner-attribution-id', bnCode);
    document.getElementsByTagName('head')[0].appendChild(script);
  };

  var getConfigAttr = function(paymentSystemId, name) {
    var PCPElementId = '#paypal-checkout-platform-' + paymentSystemId;
    var value = $(PCPElementId).attr(name) || "";
    return value;
  }

  var loadPaymentForm = function(paymentSystemId) {
    if (paypal.HostedFields.isEligible() === true) {
      $('#modal-paypal-container').css("display","none");
      $('#paypalLoading').css("display","none");
      paypal.HostedFields.render({
        createOrder: () => {
        	return fetch(LC.global.routePaths.CHECKOUT_END_ORDER, {
            	method: 'post',
            	headers: { 'content-type': 'application/json' }
          	}).then(function(res) {
            	return res.json();
            }).then(function(data) {
              paypalTransactionId = data.transactionId;
              $('#modalPayPalForm .btn-close').click();
            	return data.id;
			      });
        },
        styles: getPaymentCardStyles(),
        fields: getPaymentCardFields()
      })
      .then(function (hostedFields) {
        hostedFields.on('focus', function(event){
          if (event.fields[event.emittedBy].isValid) {
            hostedFields.removeClass(event.emittedBy, 'validation-error');
          }
        });
        hostedFields.on('blur', function(event){
          if (event.fields[event.emittedBy].isValid) {
            hostedFields.removeClass(event.emittedBy, 'validation-error');
          }
          else {
            hostedFields.addClass(event.emittedBy, 'validation-error');
          }
        });
        document.querySelector('#paypalCommerce-card-form').addEventListener('submit', (event) => {
          $('#modal-paypal-container').css("display","block");
          $('#paypalLoading').css("display","block");
          $('#ppcpAdvancedCardsBtn').prop("disabled", true);
          event.preventDefault();
          var state = hostedFields.getState();
          var formValid = Object.keys(state.fields).every(function(key){
            return state.fields[key].isValid;
          });
          if (formValid) {
            hostedFields.submit({
              contingencies:['3D_SECURE']
            }).then(payload => {
              var url = LC.global.routePaths.CHECKOUT_CONFIRM_ORDER + "?pcpTransactionId="+paypalTransactionId+"&token="+payload.orderId;
              window.location.href = url;
            });
          } else {
            LC.notify(LC.global.languageSheet.completePaymentInformation, { type: 'danger' });
            if (state.fields.number.isEmpty || !state.fields.number.isValid) {
              hostedFields.addClass('number', 'validation-error');
            }
            if (state.fields.expirationDate.isEmpty || !state.fields.expirationDate.isValid) {
              hostedFields.addClass('expirationDate', 'validation-error');
            }
            if (state.fields.cvv.isEmpty || !state.fields.cvv.isValid) {
              hostedFields.addClass('cvv', 'validation-error');
            }
            $('#ppcpAdvancedCardsBtn').prop("disabled", false);
            $('#modal-paypal-container').css("display","none");
            $('#paypalLoading').css("display","none");
          }
        });          
      });
    } else {
      redirectToDeniedOrder();
    }
  };

  $('#paypal-card_container-'+paymentSystemId).css("display","block");
  $('<span/>').appendTo($(document.body)).box({
    uid: 'modalPayPalForm',
    triggerOnClick: false,
    showFooter: false,
    source: $('#paypal-card_container-'+paymentSystemId).html(),
    type: 'html',
    callback : loadScript()
  });
};

var redirectToDeniedOrder = function() {
  window.location.href = LC.global.routePaths.CHECKOUT_DENIED_ORDER;
};

var redirectToConfirmOrder = function(transactionId) {
  window.location.href = "/checkout/confirmOrder?pcp=1&transactionId=" + transactionId;
};

var getPaymentCardStyles = function() {
    return {
      'input': {
        'font-size': '20px',
        'font-family': 'Calibri',
        'color': '#3a3a3a',
        'line-height': '40px'
      },
      ':focus': {
        'color': 'black'
      },
      '.validation-error': {
        'font-size': '20px',
        'font-family': 'Calibri',
        'color': '#f00',
        'line-height':'40px'
      }
    }  
};

var getPaymentCardFields = function() {
  if (typeof paymentCardFields != "undefined") {
    return paymentCardFields;
  } else {
    return {
      number: {
        selector: '#card-number',
        placeholder: '4111 1111 1111 1111',
      },
      cvv: {
        selector: '#cvv',
        placeholder: '111',
      },
      expirationDate: {
        selector: '#expiration-date',
        placeholder: 'mm/yyyy',
      }
    }
  };
}