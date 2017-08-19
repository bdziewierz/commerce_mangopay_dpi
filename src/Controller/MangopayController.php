<?php

namespace Drupal\commerce_mangopay\Controller;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

/**
 * This is a dummy controller for mocking an off-site gateway.
 */
class MangopayController implements ContainerInjectionInterface {

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
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $commerce_payment_gateway
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function preRegisterCard(PaymentGatewayInterface $commerce_payment_gateway, Request $request) {
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

    /** @var \Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway\MangopayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $commerce_payment_gateway->getPlugin();

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
   * Pay in callback
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $commerce_payment
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function payIn(PaymentInterface $commerce_payment, Request $request) {
    // Validate payment state. We allow only NEW payments here.
    $state = $commerce_payment->getState()->value;
    if (!in_array($state, ['new'])) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Payment has been already processed'], 400);
    }
    
    // Validate payment method
    $payment_method = $commerce_payment->getPaymentMethod();
    if (empty($payment_method)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'No payment method specified'], 400);
    }
    if ($payment_method->isExpired()) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Payment method expired'], 400);
    }

    // Fetch required information from our payment and payment_method objects
    $amount = doubleval($commerce_payment->getAmount()->getNumber()) * 100;
    $currency_code = $commerce_payment->getAmount()->getCurrencyCode();
    $card_id = $payment_method->getRemoteId();
    $user_id = $payment_method->user_id->value;
    $wallet_id = $payment_method->wallet_id->value;

    $payment_gateway = $commerce_payment->getPaymentGateway();
    /** @var \Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway\MangopayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();

    try {
      $payin = $payment_gateway_plugin->createDirectPayIn($user_id, $wallet_id, $card_id, $amount, $currency_code,
        Url::fromRoute('commerce_mangopay.process_secure_mode', ['commerce_payment' => $commerce_payment->id()], ['absolute' => TRUE])->toString());
    } catch(\Exception $e) {
      return new JsonResponse([]);
    }

    switch($payin->Status) {
      case \MangoPay\PayInStatus::Failed:
        $commerce_payment->delete();
        return new JsonResponse([
          'status' => 'Failed',
          'code' => $payin->ResultCode,
          'message' => $payin->ResultMessage]);
        break;

      // 3DS / Secure Mode, needs further processing.
      case \MangoPay\PayInStatus::Created:
        $commerce_payment->setRemoteId($payin->Id);
        $commerce_payment->save();

        if ($payin->ExecutionDetails->SecureModeNeeded && !empty($payin->ExecutionDetails->SecureModeRedirectURL)) {
          return new JsonResponse([
            'status' => 'Created',
            'code' => $payin->ResultCode,
            'message' => $payin->ResultMessage,
            'secureModeUrl' => $payin->ExecutionDetails->SecureModeRedirectURL]);
        }

        return new JsonResponse([
          'status' => 'Created',
          'code' => $payin->ResultCode,
          'message' => $payin->ResultMessage]);

        break;

      // Success, mark payment as completed and continue
      case \MangoPay\PayInStatus::Succeeded:
        $commerce_payment->setState('completed');
        $commerce_payment->setRemoteId($payin->Id);
        $commerce_payment->save();

        return new JsonResponse([
          'status' => 'Succeeded',
          'code' => $payin->ResultCode,
          'message' => $payin->ResultMessage,
          'paymentId' => $payin->Id]);
        break;

      default:
        $commerce_payment->delete();
        return new JsonResponse([
          'status' => 'Critical',
          'code' => $payin->ResultCode,
          'message' => 'Unknown critical error'], 400);
    }
  }

  /**
   * Callback method for secure mode (3DS) results.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $commerce_payment
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function processSecureMode(PaymentInterface $commerce_payment, Request $request) {

    // Validate payment state. We allow only NEW payments here.
    $state = $commerce_payment->getState()->value;
    if (!in_array($state, ['new'])) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Payment has been already processed'], 400);
    }

    // Validate payment method
    $payment_method = $commerce_payment->getPaymentMethod();
    if (empty($payment_method)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'No payment method specified'], 400);
    }
    if ($payment_method->isExpired()) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Payment method expired'], 400);
    }

    // Validate Remote ID exists
    if (empty($commerce_payment->getRemoteId())) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Payment missing remote Id'], 400);
    }

    $payment_gateway = $commerce_payment->getPaymentGateway();
    /** @var \Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway\MangopayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_gateway->getPlugin();

    // Get Remote PayIn object and check its status.
    try {
      $payin = $payment_gateway_plugin->getPayIn($commerce_payment->getRemoteId());
    } catch(\Exception $e) {
      return new JsonResponse([]);
    }

    // Build redirect URLs
    $order = $commerce_payment->getOrder();
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlow $checkout_flow */
    $checkout_flow = $order->get('checkout_flow')->entity;
    /** @var \Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesInterface $checkout_flow_plugin */
    $checkout_flow_plugin = $checkout_flow->getPlugin();
    $success_redirect_url = Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order->id(),
      'step' => 'payment',
    ], ['absolute' => TRUE])->toString();

    $failure_redirect_url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $order->id(),
      'step' => $checkout_flow_plugin->getPane('payment_information')->getStepId(),
    ], ['absolute' => TRUE])->toString();

    // Redirect accordingly.
    switch($payin->Status) {
      // Success, mark payment as completed and continue
      case \MangoPay\PayInStatus::Succeeded:
        // Mark payment's state as completed.
        $commerce_payment->setState('completed');
        $commerce_payment->save();

        return new RedirectResponse($success_redirect_url);
        break;

      case \MangoPay\PayInStatus::Failed:
      case \MangoPay\PayInStatus::Created:
        $commerce_payment->delete();
        return new RedirectResponse($failure_redirect_url);
        break;

      default:
        $commerce_payment->delete();
        return new RedirectResponse($failure_redirect_url);
    }
  }
}
