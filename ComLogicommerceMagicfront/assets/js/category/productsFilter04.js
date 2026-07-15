'use strict';

const ProductsFilter04 = {

  module: null,

  $module: undefined,

  $menu: undefined,

  $scope: undefined,

  initialized: false,

  /**
   * ProductsFilter04 initialization
   * @param {object} module - html node object, without jQuery
   */
  init(module) {
    if (!this.initialized) {
      this.module = document.querySelectorAll('.module-products-filter-04');
      this.$module = $(this.module);
      this.$menu = $('#menu-filter-04');
      this.$scope = this.$module.add(this.$menu);

      this.clearForm();
      this.nTotalAppliedFilter();
      this.moveFilters(this.$module, this.$menu);
      this.submitFilterButton();
      LC.events.addCallback('resize_debounced', this.moveFilters, [this.$module, this.$menu]);
      this.initialized = true;
    }
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

  // initMmenu() {
  //   const menuId = '#menu-extra-filter-04';
  //   new Mmenu(menuId, {
  //     offCanvas: {
  //       position: 'left-front',
  //     },
  //     navbar: {
  //       title: LC.global.languageSheet.filterBy,
  //     },
  //     navbars: [
  //       {
  //         position: 'top',
  //         content: [
  //           'title',
  //           'close',
  //         ],
  //       },
  //       {
  //         position: 'bottom',
  //         content: [
  //           `<button class="btn btn-primary filterSubmit dynamic-filter-submit" type="button">${LC.global.languageSheet.filter}</button>`
  //         ],
  //       },
  //     ],
  //     scrollBugFix: {
  //       fix: false,
  //     },
  //     hooks: {
  //       "initMenu:after": () => {
  //         const $clearFilters = $(menuId).find('.clear-filters-container > a');
  //         if ($clearFilters.length) {
  //           $(menuId)
  //             .find('.mm-navbars--bottom .mm-navbar')
  //             .prepend(`
  //               <button class="btn clearFilterButton dynamic-clear-filters" type="button">
  //                 ${LC.global.languageSheet.clearFilter}
  //               </button>`);
  //         }
  //       }
  //     },
  //   }, {
  //     offCanvas: {
  //       page: {
  //         nodetype: 'div',
  //         selector: 'body .commerce-content ',
  //       },
  //     },
  //   });
  // },

  // /**
  //  * Attach global events for
  //  * - Open Mmenu mobile
  //  * - Filter submit button
  //  * - Clear filters button
  //  */
  // triggersMmenu() {
  //   $('[data-commerce-open="menu-filter"]').on("click", (event) => {
  //     const $menuFilters = $('#menu-extra-filter-04');

  //     if ($menuFilters.length > 0) {
  //       const mmenu = $menuFilters[0].mmApi;
  //       if ($menuFilters.hasClass('mm-menu--opened')) {
  //         mmenu.close();
  //       } else {
  //         mmenu.open();
  //       }
  //     }
  //   });

  //   $('.dynamic-filter-submit').on('click', (event) => {
  //     $('#menu-extra-filter-04 form .filterSubmit').click();
  //   });

  //   $('.dynamic-clear-filters').on('click', (event) => {
  //     window.location = $('.clear-filters > a').first().attr('href');
  //   });
  // },

  // moveFilters() {
  //   const $filtersBlock = $('.translate-content-filters-04');

  //   // move filtersBlock if necessary
  //   if ($filtersBlock.length) {
  //     const $mobileCont = $('.source-filters-04-mobile'),
  //       $deskCont = $('.source-filters-04-desktop');

  //     if (window.innerWidth < 992 && !$mobileCont.find('#accordion-filter-04').length) {
  //       $mobileCont.append($filtersBlock.detach());

  //     } else if (window.innerWidth >= 992 && !$deskCont.find('#accordion-filter-04').length) {
  //       $deskCont.append($filtersBlock.detach());
  //     }
  //   }
  // },
};

CommerceDefine(ProductsFilter04, 'module', 'productsFilter04');

// Store's modules.js whitelist omits productsFilter04 → no auto-init. Init explicitly.
$(function () {
  document.querySelectorAll('[data-module="productsFilter04"]').forEach(function (el) {
    ProductsFilter04.init(el);
  });
});
