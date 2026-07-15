'use strict';

const ProductPageBottom02 = {

  module: null,

  $module: undefined,

  /**
   * ProductPageBottom02 initialization
   * @param {object} module - html node object, without jQuery
   */
  init(module) {
    this.module = module;
    this.$module = $(module);

    COMMERCE.utils.smoothScrollTo($('.tabs-scroll a.btn'));
  },

};

CommerceDefine(ProductPageBottom02, 'module', 'productPageBottom02');

document.addEventListener('DOMContentLoaded', function () {
    if (typeof ProductPageBottom02 !== 'undefined') {
        document.querySelectorAll('[data-module="productPageBottom02"]').forEach(function (el) { ProductPageBottom02.init(el); });
    }
});
