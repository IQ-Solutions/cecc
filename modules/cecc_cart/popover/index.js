import tippy from "tippy.js";
import 'tippy.js/dist/tippy.css';
import "tippy.js/dist/border.css";

((Drupal, drupalSettings, tippy) => {
  Drupal.AjaxCommands.prototype.ceccPopover = ((ajax, response, status) => {
    const submitButton = document.querySelector(ajax.selector);
    tippy(submitButton, {
      allowHTML: true,
      content: response.content,
      interactive: true,
      maxWidth: 300,
      placement: 'top-end',
      trigger: "manual",
      theme: response.type,
    });

    submitButton._tippy.show();

    setTimeout(() => {
      submitButton._tippy.hide();
    }, 10000);

  });
})(Drupal, drupalSettings, tippy);
