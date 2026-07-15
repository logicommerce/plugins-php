'use strict';

const ProductPageTop02 = {

  module: null,

  $module: undefined,

  mainGalleryInstance: null,

  additionalGalleryInstance: null,

  /**
   * ProductPageTop02 initialization
   * @param {object} module - html node object, without jQuery
   */
  init(module) {
    this.module = module;
    this.$module = $(module);

    if (!this.$module.hasClass('initialized')) {
      this.additionalGallery();
      this.mainGallery();
      this.viewLongDescBtn();
      this.$module.addClass('initialized');
    }
  },

  mainGallery() {
    this.mainGalleryInstance = new Swiper('#swiper-main-gallery-02 .swiper', {
      loop: false,
      pagination: {
        el: '#swiper-main-gallery-02 .swiper-pagination',
        type: 'bullets',
        clickable: true,
      },
      thumbs: {
        swiper: this.additionalGalleryInstance,
      },
    });
  },

  additionalGallery() {
    if (document.getElementById('swiper-additional-gallery-02')) {
      this.additionalGalleryInstance = new Swiper('#swiper-additional-gallery-02 .swiper', {
        direction: 'vertical',
        loop: false,
        slidesPerView: 5,
        spaceBetween: 12,
        mousewheel: true,
        navigation: {
          nextEl: '#swiper-additional-gallery-02 .swiper-button-next',
          prevEl: '#swiper-additional-gallery-02 .swiper-button-prev',
        },
      });
    }
  },

  viewLongDescBtn() {
    COMMERCE.utils.smoothScrollTo($('.scroll-to-product-long-description'), {
      beforeScroll: (el) => {
        if (window.innerWidth >= 992) {
          $('.product-bottom-tabs [href="#product-long-description"]').tab('show');
        } else {
          $('.product-bottom-tabs #collap-prod-long-desc').collapse('show');
        }
      },
    });
  },
};

CommerceDefine(ProductPageTop02, 'module', 'productPageTop02');

// MFF self-init guard: store modules.js may not whitelist this module, so init explicitly.
document.addEventListener('DOMContentLoaded', function () {
  if (typeof ProductPageTop02 !== 'undefined') {
    document.querySelectorAll('[data-module="productPageTop02"]').forEach(function (el) {
      ProductPageTop02.init(el);
    });
  }
});
