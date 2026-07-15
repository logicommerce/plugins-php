'use strict';

const ProductsFilter03 = {

  module: null,

  $module: undefined,

  $menu: undefined,

  $scope: undefined,

  /**
   * ProductsFilter03 initialization
   * @param {object} module - html node object, without jQuery
   */
  init(module) {
    this.module = module;
    this.$module = $(module);
    this.$menu = $('#menu-filter-03');
    this.$scope = this.$module.add(this.$menu);

    this.clearForm();
    this.nTotalAppliedFilter();
    this.moveFilters(this.$module, this.$menu);
    this.submitFilterButton();
    LC.events.addCallback('resize_debounced', this.moveFilters, [this.$module, this.$menu]);
  },

  clearForm() {
    this.$module.find('[data-lc-form="productsFilter"] .form-message').remove();
  },

  nTotalAppliedFilter() {
    let total = this.$scope.find('.n-applied-filter:not(.n-0)').length;

    this.$scope
      .find('.n-total-applied-filter')
      .removeClass((i, className) =>
        (className.match(/n\-[0-9]*/g) || []).join(' ')
      )
      .addClass('n-' + total)
      .html(total);
  },

  submitFilterButton() {
    this.$scope.find('.filter-form-submit-custom').click((event) => {
      this.$scope.find('.filterSubmit').click();
    });
  },

  /**
   * Move filters between mobile container and desktop container
   * @param {object} $module 
   * @param {object} $menu 
   */
  moveFilters($module, $menu) {
    const $filtersBlock = $module.add($menu).find('.content-filters-to-move');

    if ($filtersBlock.length) {
      const $mobileCont = $menu.find('.content-filters-place-mobile'),
        $deskCont = $module.find('.content-filters-place-desktop');

      if (window.innerWidth < 992 && !$mobileCont.find('.content-filters-to-move').length) {
        $mobileCont.append($filtersBlock.detach());

      } else if (window.innerWidth >= 992 && !$deskCont.find('.content-filters-to-move').length) {
        $deskCont.append($filtersBlock.detach());
      }
    }
  },
};

CommerceDefine(ProductsFilter03, 'module', 'productsFilter03');

// The store's modules.js whitelist (COMMERCE_AVAILABLE_MODULES) does not list
// productsFilter03, so the commerce module system never auto-inits it. Init it
// explicitly on DOM ready.
$(function () {
  document.querySelectorAll('[data-module="productsFilter03"]').forEach(function (el) {
    ProductsFilter03.init(el);
  });
});
