<?php

namespace Drupal\cecc_shipping\Plugin\Commerce\CheckoutPane;

use Drupal\cecc_shipping\Plugin\Commerce\PaymentGateway\ShippingAccountGateway;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;

/**
 * Adds payment intent confirmation for Stripe.
 *
 * This checkout pane is required. It ensures that the last step in the checkout
 * performs authentication and confirmation of the payment intent. If the
 * customer's card is not enrolled in 3DS then the form will submit as normal.
 * Otherwise a modal will appear for the customer to authenticate and approve
 * of the charge.
 *
 * @CommerceCheckoutPane(
 *   id = "cecc_shipping_account_review",
 *   label = @Translation("Shipping account review"),
 *   default_step = "review",
 *   wrapper_element = "container",
 * )
 */
class ShippingAccountReview extends CheckoutPaneBase {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    $gateway = $this->order->get('payment_gateway');
    if ($gateway->isEmpty() || empty($gateway->entity)) {
      return FALSE;
    }
    return $gateway->entity->getPlugin() instanceof ShippingAccountGateway;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    // The only point of this pane is passing the stripe payment intent ID (and
    // some other data) to js when first loading the page (and not when
    // submitting the form).
    if (!empty($form_state->getValues()) || !empty($form_state->getUserInput())) {
      return $pane_form;
    }

    /** @var \Drupal\cecc_shipping\Plugin\Commerce\PaymentGateway\ShippingAccountInterface $shippingAccountPlugin */
    $shippingAccountPlugin = $this->order->get('payment_gateway')->entity->getPlugin();

    $payment_process_pane = $this->checkoutFlow->getPane('payment_process');
    assert($payment_process_pane instanceof CheckoutPaneInterface);
    $capture = $payment_process_pane->getConfiguration()['capture'];
    $intent = $stripe_plugin->createPaymentIntent($this->order, $capture);

    if ($intent->status === PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD) {
      $payment_method = $this->order->get('payment_method')->entity;
      assert($payment_method instanceof PaymentMethodInterface);
      $payment_method_remote_id = $payment_method->getRemoteId();
      $intent = PaymentIntent::update($intent->id, [
        'payment_method' => $payment_method_remote_id,
      ]);
    }

    // To display validation errors.
    $pane_form['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<div id="payment-errors"></div>',
      '#weight' => -200,
    ];

    $pane_form['#attached']['library'][] = 'commerce_stripe/stripe';
    $pane_form['#attached']['library'][] = 'commerce_stripe/checkout_review';
    $pane_form['#attached']['drupalSettings']['commerceStripe'] = [
      'publishableKey' => $stripe_plugin->getPublishableKey(),
      'clientSecret' => $intent->client_secret,
      'buttonId' => $this->configuration['button_id'],
      'orderId' => $this->order->id(),
      'paymentMethod' => $intent->payment_method,
    ];
    $profiles = $this->order->collectProfiles();
    if (isset($profiles['shipping']) && !$profiles['shipping']->get('address')->isEmpty()) {
      $pane_form['#attached']['drupalSettings']['commerceStripe']['shipping'] = $profiles['shipping']->get('address')->first()->toArray();
    }

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($this->order);
    $cacheability->setCacheMaxAge(0);
    $cacheability->applyTo($pane_form);
    return $pane_form;
  }

}
