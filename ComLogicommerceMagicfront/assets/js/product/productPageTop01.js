'use strict';

const ProductPageTop01 = {

  module: null,

  $module: undefined,

  mainGalleryInstance: null,

  additionalGalleryInstance: null,

  /**
   * ProductPageTop01 initialization
   * @param {object} module - html node object, without jQuery
   */
  init(module) {
    this.module = module;
    this.$module = $(module);

    if (!this.$module.hasClass('initialized')) {
      this.additionalGallery();
      this.mainGallery();
      this.viewReviewsBtn();
      this.$module.addClass('initialized');
    }
  },

  mainGallery() {
    this.mainGalleryInstance = new Swiper('#swiper-main-gallery-01 .swiper', {
      loop: false,
      pagination: {
        el: '#swiper-main-gallery-01 .swiper-pagination',
        type: 'bullets',
        clickable: true,
      },
      thumbs: {
        swiper: this.additionalGalleryInstance,
      },
    });
  },

  additionalGallery() {
    if (document.getElementById('swiper-additional-gallery-01')) {
      this.additionalGalleryInstance = new Swiper('#swiper-additional-gallery-01 .swiper', {
        loop: false,
        watchSlidesProgress: true,
        slidesPerView: 4,
        spaceBetween: 12,
        navigation: {
          nextEl: '#swiper-additional-gallery-01 .swiper-button-next',
          prevEl: '#swiper-additional-gallery-01 .swiper-button-prev',
        },
        breakpoints: {
          1400: {
            slidesPerView: 5,
          },
        },
      });
    }
  },

  viewReviewsBtn() {
    COMMERCE.utils.smoothScrollTo($('.scroll-to-product-reviews'), {
      beforeScroll: (el) => {
        if (window.innerWidth >= 992) {
          $('.product-bottom-tabs [href="#product-reviews"]').tab('show');
        } else {
          $('.product-bottom-tabs #collap-prod-reviews').collapse('show');
        }
      },
    });
  },
};

CommerceDefine(ProductPageTop01, 'module', 'productPageTop01');

// MFF self-init guard: store modules.js may not whitelist this module, so init explicitly.
document.addEventListener('DOMContentLoaded', function () {
  if (typeof ProductPageTop01 !== 'undefined') {
    document.querySelectorAll('[data-module="productPageTop01"]').forEach(function (el) {
      ProductPageTop01.init(el);
    });
  }
});
