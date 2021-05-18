(function ($, Drupal, drupalSettings) {
  Drupal.AjaxCommands.prototype.example = function (ajax, response, status) {
    console.log(response);
    console.log(ajax);
    console.log(status);
  }
})(jQuery, Drupal, drupalSettings);
