'use strict';

const ProductPageBottom01 = {

  module: null,

  $module: undefined,

  /**
   * ProductPageBottom01 initialization
   * @param {object} module - html node object, without jQuery
   */
  init(module) {
    this.module = module;
    this.$module = $(module);

    this.accordionMobile();
  },

  accordionMobile() {
    this.$module.find('.btn-bottom-collapse').on('click', (event) => {
      var $save = $(event.currentTarget);
      this.$module
        .find('.btn-bottom-collapse')
        .not('.collapsed')
        .not($save)
        .each((i, el) => {
          $($(el).data('bs-target')).collapse('hide');
        });
    });
  },
};

CommerceDefine(ProductPageBottom01, 'module', 'productPageBottom01');

document.addEventListener('DOMContentLoaded', function () {
    if (typeof ProductPageBottom01 !== 'undefined') {
        document.querySelectorAll('[data-module="productPageBottom01"]').forEach(function (el) { ProductPageBottom01.init(el); });
    }
});
