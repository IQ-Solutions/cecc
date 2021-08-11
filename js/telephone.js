(function ($) {
  "use strict";

  Drupal.behaviors.intl_tel_input = {
    attach: function (context, settings) {
      var options = {
        onKeyUp: function (cep, e, field, options) {
          $(".form-tel").mask('+1 (000) 000-0000', options);
        }
      }

      $(".form-tel").mask(
        "+1 (000) 000-0000",
        options
      );

    }
  }
}(jQuery));
