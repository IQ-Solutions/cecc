(function ($) {
  "use strict";

  Drupal.behaviors.intl_tel_input = {
    attach: function (context, settings) {
      var options = {
        onKeyUp: function (cep, e, field, options) {
          $("#edit-po-contact-information-phone-phone-number").mask('+1 (000) 000-0000', options);
        }
      }

      $("#edit-po-contact-information-phone-phone-number").mask(
        "+1 (000) 000-0000",
        options
      );

    }
  }
}(jQuery));
