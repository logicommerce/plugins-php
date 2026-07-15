'use strict';

const ProductsSlider01 = {

  module: null,

  $module: undefined,

  /**
   * ProductsSlider01 initialization
   * @param {object} module - html node object, without jQuery
   */
  init(module) {
    this.module = module;
    this.$module = $(module);

    if (this.$module.data('initialized') !== true) {
      this.initSwiper();
      this.$module.data('initialized', true);
    }
  },

  initSwiper() {
    new Swiper(this.module.querySelector('.swiper'), {
      loop: false,
      spaceBetween: 12,
      navigation: {
        nextEl: this.module.querySelector('.swiper-button-next'),
        prevEl: this.module.querySelector('.swiper-button-prev'),
      },
      focusableElements: 'input, select, option, textarea, video, label',
      watchSlidesProgress: true,
      // pagination: {
      //   el: this.module.querySelector('.swiper-pagination'),
      //   clickable: true,
      // },
      slidesPerView: 1,
      breakpoints: {
        480: {
          slidesPerView: 2,
        },
        768: {
          slidesPerView: 3,
        },
        992: {
          slidesPerView: 4,
        },
      },
    });
  },
};

CommerceDefine(ProductsSlider01, 'module', 'productsSlider01');

document.addEventListener('DOMContentLoaded', function () {
    if (typeof ProductsSlider01 !== 'undefined') {
        document.querySelectorAll('[data-module="productsSlider01"]').forEach(function (el) { ProductsSlider01.init(el); });
    }
});
