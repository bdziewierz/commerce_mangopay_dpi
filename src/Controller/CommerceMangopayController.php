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
   * Callback method which pre registeres user's card
   * and creates user - wallet combo if it doesn't exist.
   *
   * @param $payment_gateway_id
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function preRegisterCard($payment_gateway_id, Request $request) {
    // TODO: Add some kind of security so that this endpoint is not be called willy-nilly

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment */
    $payment_gateway = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->load($payment_gateway_id);
    if (empty($payment_gateway)) {
      return new JsonResponse(NULL, 404);
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
    // TODO: Handle errors - https://docs.mangopay.com/guide/errors

    /** @var \Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway\OnsiteInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();

    // Create user for payment
    /// TODO: Fix hardcoded date of birth, please
    $user = $payment_gateway_plugin->createNaturalUser($first_name, $last_name, strtotime('1980-08-25'), $email, $country, $address_line1, $address_line2, $city, $postal_code, '', '', 'buyer');

    // Create Wallet for the user
    $wallet = $payment_gateway_plugin->createWallet($user->Id, $currency_code, "Buyer wallet", "buyer wallet");

    // Initiate card registration
    $card_register = $payment_gateway_plugin->createCardRegistration($user->Id, $currency_code, $card_type, "buyer card");

    // Send response to the browser
    return new JsonResponse([
        'userId' => $user->Id,
        'walletId' => $wallet->Id,
        'cardRegistrationURL' => $card_register->CardRegistrationURL,
        'preregistrationData' => $card_register->PreregistrationData,
        'cardRegistrationId' => $card_register->Id,
        'cardType' => $card_register->CardType,
        'accessKey' => $card_register->AccessKey
      ]);
  }

  /**
   * Callback method for secure mode (3DS) results.
   *
   * @param $payment_gateway_id
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function secureModeCallback($payment_gateway_id, Request $request) {
    // TODO: Add some kind of security so that this endpoint is not be called willy-nilly

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment */
    $payment_gateway = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->load($payment_gateway_id);
    if (empty($payment_gateway)) {
      return new JsonResponse(NULL, 404);
    }

    // Capture user details passed in the request
    $currency_code = $request->get('currency_code');
    if (empty($currency_code)) {
      return new JsonResponse(NULL, 400);
    }

    /** @var \Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway\OnsiteInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();

    // Send response to the browser
    return new JsonResponse([]);
  }
}
