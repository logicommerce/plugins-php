'use strict';

const ProductPageTop03 = {

  module: null,

  $module: undefined,

  mainGalleryInstance: null,

  /**
   * ProductPageTop03 initialization
   * @param {object} module - html node object, without jQuery
   */
  init(module) {
    this.module = module;
    this.$module = $(module);

    if (!this.$module.hasClass('initialized')) {
      this.mainGallery();
      this.$module.addClass('initialized');
    }
  },

  mainGallery() {
    this.mainGalleryInstance = new Swiper('.mff-module-product-page-top-03 .mff-swiper-main-gallery', {
      loop: false,
      pagination: {
        el: '.mff-module-product-page-top-03 .swiper-pagination',
        type: 'bullets',
        clickable: true,
      },
      on: {
        slideChangeTransitionEnd: function (swiper) {
          if (window.innerWidth >= 992) {
            const activeArr = swiper.slides.filter(slide => slide.matches('.swiper-slide-active.swiper-slide-option'));
            if (activeArr.length) {
              COMMERCE.utils.scrollTo({}, $(activeArr[0]));
            }
          }
        },
      },
    });
  },

  affixColumn() {
    const element = document.querySelector('.mff-module-product-page-top-03 .mff-product-info-affix');
    if (element && navigator.userAgent.match(/iPad/i) === null && COMMERCE.stickyHeight.desktop > 0) {
      try {
        $(element).stickySidebar({
          resizeSensor: true,
          containerSelector: '.mff-module-product-page-top-03 .mff-module-inset',
          topSpacing: COMMERCE.stickyHeight.desktop,
          bottomSpacing: 0,
        });
        LC.events.removeCallback('scroll_throttled', () => this.affixColumn());
      } catch (e) {
        console.error(
          'ProductPageTop03 affixColumn require "ResizeSensor.min.js" and "jquery.sticky-sidebar.min.js" libraries'
        );
      }
    }
  },
};

CommerceDefine(ProductPageTop03, 'module', 'productPageTop03');

// MFF self-init guard: store modules.js may not whitelist this module, so init explicitly.
document.addEventListener('DOMContentLoaded', function () {
  if (typeof ProductPageTop03 !== 'undefined') {
    document.querySelectorAll('[data-module="productPageTop03"]').forEach(function (el) {
      ProductPageTop03.init(el);
    });
  }
});
