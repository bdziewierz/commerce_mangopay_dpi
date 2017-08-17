<?php

namespace Drupal\commerce_mangopay\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * This is a dummy controller for mocking an off-site gateway.
 */
class CommerceMangopayController implements ContainerInjectionInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a new DummyRedirectController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->currentRequest = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * Callback method which creates MANGOPAY user
   * before registering his credit card.
   *
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   */
  public function getUser($payment_gateway_id, Request $request) {
    // TODO: Add some kind of security so that this endpoint is not be called willy-nilly

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment */
    $payment_gateway = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->load($payment_gateway_id);
    if (empty($payment_gateway)) {
      return new JsonResponse(NULL, 400);
    }

    // Capture user details passed in the request
    $currency_code = $request->get('currency_code');
    if (empty($currency_code)) {
      return new JsonResponse(NULL, 400);
    }

    $first_name = $request->get('first_name');
    if (empty($first_name)) {
      return new JsonResponse(NULL, 400);
    }

    $last_name = $request->get('last_name');
    if (empty($last_name)) {
      return new JsonResponse(NULL, 400);
    }

    $email = $request->get('email');
    if (empty($email)) {
      return new JsonResponse(NULL, 400);
    }

    $address_line1 = $request->get('address_line1');
    $address_line2 = $request->get('address_line2');
    if (empty($address_line1)) {
      return new JsonResponse(NULL, 400);
    }

    $city = $request->get('city');
    $postal_code = $request->get('postal_code');
    if (empty($city)) {
      return new JsonResponse(NULL, 400);
    }

    $country = $request->get('country');
    if (empty($country)) {
      return new JsonResponse(NULL, 400);
    }

    $card_type = $request->get('card_type');
    if (empty($card_type)) {
      return new JsonResponse(NULL, 400);
    }

    // TODO: Check if user exists in MANGOPAY
    // TODO: if not, create it
    // TODO: if yes, retrieve it

    /** @var \Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway\OnsiteInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();

    // Create instance of MangoPayApi SDK
    $mangopay_api = $payment_gateway_plugin->createMangopayApi();

    // Create user for payment
    $user = $payment_gateway_plugin->createNaturalUser($mangopay_api, $first_name, $last_name, strtotime('1980-08-25'), $email, $country, $address_line1, $address_line2, $city, $postal_code, '', '', 'buyer');

    // Create Wallet for the user
    $wallet = $payment_gateway_plugin->createWallet($mangopay_api, $user->Id, $currency_code, "Buyer wallet", "buyer wallet");

    // Initiate card registration
    $cardRegister = $payment_gateway_plugin->createCardRegistration($mangopay_api, $user->Id, $currency_code, $card_type, "buyer card");

    // Send response to the browser
    return new JsonResponse([
        'userId' => $user->Id,
        'walletId' => $wallet->Id,
        'cardRegistrationURL' => $cardRegister->CardRegistrationURL,
        'preregistrationData' => $cardRegister->PreregistrationData,
        'cardRegistrationId' => $cardRegister->Id,
        'cardType' => $cardRegister->CardType,
        'accessKey' => $cardRegister->AccessKey
      ]);
  }
}
