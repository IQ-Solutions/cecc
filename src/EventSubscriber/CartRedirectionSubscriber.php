<?php

namespace Drupal\cecc\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Add to cart redirection subscriber.
 */
class CartRedirectionSubscriber implements EventSubscriberInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The route provider to load routes by name.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The config factory service
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * CartEventSubscriber constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The route provider.
   */
  public function __construct(
    RequestStack $requestStack,
    RouteProviderInterface $routeProvider,
    ConfigFactoryInterface $config_factory
  ) {
    $this->requestStack = $requestStack;
    $this->routeProvider = $routeProvider;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      CartEvents::CART_ENTITY_ADD => 'doRedirect',
      KernelEvents::RESPONSE => [
        'checkRedirectIssued',
        -10,
      ],
    ];

    return $events;
  }

  /**
   * Redirect user to cart.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The add to cart event.
   */
  public function doRedirect(CartEntityAddEvent $event) {
    $ceccConfig = $this->configFactory->get('cecc.settings');

    if ($ceccConfig->get('add_to_cart_dest') == 'cart') {
      $redirection_url = Url::fromRoute('commerce_cart.page')->toString();

      $this->requestStack->getCurrentRequest()->attributes
        ->set('commerce_cart_redirection_url', $redirection_url);
    }
  }

  /**
   * Checks if a redirect url has been set.
   *
   * Redirects to provided url if there is one.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function checkRedirectIssued(ResponseEvent $event) {
    $request = $event->getRequest();
    $redirectUrl = $request->attributes->get('commerce_cart_redirection_url');
    $cartConfig = $this->configFactory->get('cecc_cart.settings');
    $usingAjax = $cartConfig->get('use_ajax') !== 0;

    if (isset($redirectUrl) && !$usingAjax) {
      $event->setResponse(new RedirectResponse($redirectUrl));
    }
  }

}
