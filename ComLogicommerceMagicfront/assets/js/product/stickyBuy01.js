'use strict';

/**
 * StickyBuy01 Add-on
 * Sticky buy bar for mobile check confluence documentation
 * @version 1.0.2
 */
let StickyBuy01 = {

  /**
   * StickyBuy01 initialization
   */
  init() {
    this.eventListeners();
    $('.product-quick-buy-backdrop').removeClass('no-init');
  },

  /**
   * Function called into onChangeCallback BuyForm callback
   * @param {object} data
   * @param {object} form
   */
  onChangeCallback(data, form) {
    const selectedOptions = COMMERCE.utils.getSelectedOptions(data.combinationData.options),
      totalOptions = selectedOptions.length + COMMERCE.utils.getRequiredOptions(data.combinationData.options).length,
      requiredOptions = COMMERCE.utils.getRequiredOptions(data.combinationData.options).length,
      $pageTop = this.getPageTopEl(form);

    if (totalOptions === 1) {
      const $productOptionValue = $pageTop.find(`.productOption${selectedOptions[0].option.id}`),
        optionValueData = $productOptionValue.find('.productOptionSelected').data('lc-product-option-value');

      if (optionValueData) {
        $pageTop.find('.select-option-value .value').html(optionValueData.value);
      }
      $pageTop.removeClass('select-option').addClass('selected-option');

      if ($productOptionValue.hasClass('productOptionRadioValueNotAvailable')) {
        $pageTop.addClass('selected-option-no-stock');
      } else {
        $pageTop.removeClass('open-quick-buy selected-option-no-stock');
      }
    } else if (requiredOptions === 0) {
      $pageTop.removeClass('select-option').addClass('selected-option');
      if (data.combinationData.stock.units === 0) {
        $pageTop.addClass('selected-option-no-stock');
      } else {
        $pageTop.removeClass('open-quick-buy selected-option-no-stock');
      }
    }
  },

  /**
   * Global document listeners on quick buy system
   */
  eventListeners() {
    $(document).on('click', '.module-product-page-top .quick-buy-submit', (event) => {
      const $pageTop = this.getPageTopEl(event.currentTarget);

      if ($pageTop.hasClass('select-option') || $pageTop.hasClass('selected-option-no-stock')) {
        $pageTop.toggleClass('open-quick-buy');
      }
    });

    $(document).on('click', '.select-option-value', (event) => {
      const $pageTop = this.getPageTopEl(event.currentTarget);
      $pageTop.toggleClass('open-quick-buy');
    });

    // Close
    $(document).on('click', '.module-product-page-top .btn-close, .product-quick-buy-backdrop', (event) => {
      event.preventDefault();

      const $pageTop = this.getPageTopEl(event.currentTarget);
      $pageTop.removeClass('open-quick-buy');
    });
  },

  /**
   * Returns product page top section from target
   * @param {object} target
   * @returns {object} jQuery element
   */
  getPageTopEl(target) {
    return $(target).closest('.module-product-page-top');
  },
};

CommerceDefine(StickyBuy01, 'stickyBuy01');

// MFF self-init guard: store modules.js may not whitelist this add-on, so init explicitly.
document.addEventListener('DOMContentLoaded', function () {
  if (typeof StickyBuy01 !== 'undefined') {
    StickyBuy01.init();
  }
});
