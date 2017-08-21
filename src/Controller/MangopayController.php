<?php

namespace Drupal\commerce_mangopay\Controller;

use CommerceGuys\Intl\Currency\Currency;
use DateTime;
use DateTimeZone;
use Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway\Mangopay;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mongopay controller
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
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Currency code is required'], 400);
    }

    /** @var \Drupal\commerce_price\Entity\Currency $currency */
    $currency = \Drupal::entityTypeManager()->getStorage('commerce_currency')->load($currency_code);
    if (empty($currency)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Currency code is invalid'], 400);
    }

    $first_name = $request->get('first_name');
    if (empty($first_name)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'First name is required'], 400);
    }

    $last_name = $request->get('last_name');
    if (empty($last_name)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Last name is required'], 400);
    }

    $email = $request->get('email');
    if (empty($email)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Email is required'], 400);
    }

    $dob = $request->get('dob');
    if (empty($dob)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Date of birth is required'], 400);
    }
    $dob = new DateTime($dob . ' 00:00:00', new DateTimeZone('UTC'));

    $address_line1 = $request->get('address_line1');
    $address_line2 = $request->get('address_line2');
    if (empty($address_line1)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Address line 1 is required'], 400);
    }

    $city = $request->get('city');
    $postal_code = $request->get('postal_code');
    if (empty($city)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'City is required'], 400);
    }

    $country = $request->get('country');
    if (empty($country)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Country is required'], 400);
    }

    $nationality = $request->get('nationality');
    if (empty($nationality)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Nationality is required'], 400);
    }

    $card_type = $request->get('card_type');
    if (empty($card_type)) {
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Card type is required'], 400);
    }

    /** @var \Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway\MangopayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $commerce_payment_gateway->getPlugin();
    $account = \Drupal::currentUser();
    $user = NULL;
    $mangopay_user = NULL;

    // Load user if authenticated.
    if ($account->isAuthenticated()) {
      $user = User::load($account->id());
    }

    // Check if the currently logged in user has already Remote Id set.
    // If yes, try to fetch the MANGOPAY user from the API.
    if ($user) {
      /** @var \Drupal\commerce\Plugin\Field\FieldType\RemoteIdFieldItemListInterface $remote_ids */
      $remote_ids = $user->get('commerce_remote_id');
      $mangopay_remote_id = $remote_ids->getByProvider($commerce_payment_gateway->id() . '|' . $payment_gateway_plugin->getMode());
      if (!empty($mangopay_remote_id)) {
        try {
          $mangopay_user = $payment_gateway_plugin->getUser($mangopay_remote_id);
        } catch(\Exception $e) {
          \Drupal::logger('commerce_mangopay')->notice(sprintf('Unable to retrieve MANGOPAY user %s while registering a card: %s: %s', $mangopay_remote_id, $e->getCode(), $e->getMessage()));
        }
      }
    }

    // IF no MANGOPAY user retrieved, try to create it.
    if (!$mangopay_user) {
      // Create user for payment if there is no Remote Id already stored in Drupal.
      try {
        $mangopay_user = $payment_gateway_plugin->createNaturalUser($first_name, $last_name, $email, $dob->format('U'), $nationality, $country, $address_line1, $address_line2, $city, $postal_code, '', '', $payment_gateway_plugin->getTag());
      } catch(\Exception $e) {
        \Drupal::logger('commerce_mangopay')->error(sprintf('Unable to create MANGOPAY user while registering a card: %s: %s', $e->getCode(), $e->getMessage()));
        return new JsonResponse([
          'status' => 'Critical',
          'message' => 'Unable to create MANGOPAY user'], 500);
      }

      // Set MANGOPAY User Id on the user object if the account is logged in.
      if ($user) {
        /** @var \Drupal\commerce\Plugin\Field\FieldType\RemoteIdFieldItemListInterface $remote_ids */
        $remote_ids = $user->get('commerce_remote_id');
        $remote_ids->setByProvider($commerce_payment_gateway->id() . '|' . $payment_gateway_plugin->getMode(), $mangopay_user->Id);
        $user->save();
      }
    }

    // Check if user already has an active wallet for Drupal Commerce with specified currency.
    // If yes, use it. Otherwise, create a new one.
    $mangopay_wallet = NULL;
    try {
      $wallets = $payment_gateway_plugin->getWallets($mangopay_user->Id);
      foreach($wallets as $wallet) {
        if ($wallet->Tag == $payment_gateway_plugin->getTag()
          && $wallet->Currency == $currency_code) {
          $mangopay_wallet = $wallet;
          continue;
        }
      }
    } catch(\Exception $e) {
      \Drupal::logger('commerce_mangopay')->notice(sprintf('Unable to retrieve MANGOPAY wallets for user %s', $mangopay_user->Id));
    }
    
    // If yes, use it, otherwise create a new one.
    if (!$mangopay_wallet) {
      try {
        $mangopay_wallet = $payment_gateway_plugin->createWallet($mangopay_user->Id, $currency_code, sprintf('%s wallet', $currency->getName()), $payment_gateway_plugin->getTag());
      } catch (\Exception $e) {
        \Drupal::logger('commerce_mangopay')
          ->error(sprintf('Unable to create MANGOPAY wallet for user %s while registering a card: %s: %s', $mangopay_user->Id, $e->getCode(), $e->getMessage()));
        return new JsonResponse([
          'status' => 'Critical',
          'message' => 'Unable to create MANGOPAY wallet'
        ], 500);
      }
    }

    // Initiate card registration
    try {
      $card_register = $payment_gateway_plugin->createCardRegistration($mangopay_user->Id, $currency_code, $card_type, $payment_gateway_plugin->getTag());
    } catch(\Exception $e) {
      \Drupal::logger('commerce_mangopay')->error(sprintf('Unable to register card for user %s and wallet %s: %s: %s', $mangopay_user->Id, $mangopay_wallet->Id, $e->getCode(), $e->getMessage()));
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Unable to register card'], 500);
    }

    // TODO: Handle errors better. Maybe some are recoverable? - https://docs.mangopay.com/guide/errors
    
    // Send response to the browser
    return new JsonResponse([
        'userId' => $mangopay_user->Id,
        'walletId' => $mangopay_wallet->Id,
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
    $unexpected_error_message = t('Unexpected error has occurred while processing your transaction.  No funds were taken from your card. Please confirm your card details are correct or try a different card.');
    $rejected_error_message = t('The transaction has been rejected. No funds were taken from your card. Please confirm your card details are correct or try a different card.');

    // Validate payment state. We allow only NEW payments here.
    $state = $commerce_payment->getState()->value;
    if (!in_array($state, ['new'])) {
      drupal_set_message($unexpected_error_message, 'error');
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Payment has been already processed'], 400);
    }
    
    // Validate payment method
    $payment_method = $commerce_payment->getPaymentMethod();
    if (empty($payment_method)) {
      drupal_set_message($unexpected_error_message, 'error');
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'No payment method specified'], 400);
    }
    if ($payment_method->isExpired()) {
      drupal_set_message($unexpected_error_message, 'error');
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
      drupal_set_message($unexpected_error_message, 'error');
      \Drupal::logger('commerce_mangopay')->error(sprintf('Unable to create Direct PayIn for card %s: %s: %s', $card_id, $e->getCode(), $e->getMessage()));
      return new JsonResponse([
        'status' => 'Critical',
        'code' => $e->getCode(),
        'message' => $e->getMessage()], 500);
    }

    switch($payin->Status) {
      case \MangoPay\PayInStatus::Failed:
        $commerce_payment->delete();
        // TODO: Display more descriptive messages here for the transaction errors: https://docs.mangopay.com/guide/errors
        // TODO: On some responses, shall we remove payment methods which are permanently failing? i.e. card becomes automatically inactive when failed for the first time it's tried.
        drupal_set_message($rejected_error_message, 'error');
        \Drupal::logger('commerce_mangopay')->warning(sprintf('Pay In Failure: %s: %s', $payin->ResultCode, $payin->ResultMessage));
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
        else {
          $commerce_payment->delete();
          drupal_set_message($unexpected_error_message, 'error');
          \Drupal::logger('commerce_mangopay')->warning(sprintf('No SecureModeRedirectURL provided for Created response: %s: %s', $payin->ResultCode, $payin->ResultMessage));
          return new JsonResponse([
            'status' => 'Critical',
            'code' => $payin->ResultCode,
            'message' => 'Unknown critical error'], 500);
        }

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
        drupal_set_message($unexpected_error_message, 'error');
        \Drupal::logger('commerce_mangopay')->error(sprintf('Pay In Error: %s: %s', $payin->ResultCode, $payin->ResultMessage));
        return new JsonResponse([
          'status' => 'Critical',
          'code' => $payin->ResultCode,
          'message' => 'Unknown critical error'], 500);
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
    $unexpected_error_message = t('Unexpected error has occurred while processing your transaction.  No funds were taken from your card. Please confirm your card details are correct or try a different card.');
    $rejected_error_message = t('The transaction has been rejected. No funds were taken from your card. Please confirm your card details are correct or try a different card.');

    // Validate payment state. We allow only NEW payments here.
    $state = $commerce_payment->getState()->value;
    if (!in_array($state, ['new'])) {
      drupal_set_message($unexpected_error_message, 'error');
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Payment has been already processed'], 400);
    }

    // Validate payment method
    $payment_method = $commerce_payment->getPaymentMethod();
    if (empty($payment_method)) {
      drupal_set_message($unexpected_error_message, 'error');
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'No payment method specified'], 400);
    }
    if ($payment_method->isExpired()) {
      drupal_set_message($unexpected_error_message, 'error');
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'Payment method expired'], 400);
    }

    // Validate Remote ID exists
    if (empty($commerce_payment->getRemoteId())) {
      drupal_set_message($unexpected_error_message, 'error');
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
      drupal_set_message($unexpected_error_message, 'error');
      \Drupal::logger('commerce_mangopay')->error(sprintf('Unknown critical error occurred while fetching pay in object %s', $commerce_payment->getRemoteId()));
      return new JsonResponse([
        'status' => 'Critical',
        'message' => 'MANGOPAY Pay In object not found'], 500);
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
    ], ['absolute' => TRUE, 'query' => ['payment_id' => $payin->Id]])->toString();

    $failure_redirect_url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $order->id(),
      'step' => $checkout_flow_plugin->getPane('payment_information')->getStepId(),
    ], ['absolute' => TRUE])->toString();

    // Redirect accordingly.
    switch($payin->Status) {
      case \MangoPay\PayInStatus::Succeeded:
        // Mark payment's state as completed.
        $commerce_payment->setState('completed');
        $commerce_payment->save();

        return new RedirectResponse($success_redirect_url);
        break;

      case \MangoPay\PayInStatus::Failed:
        // TODO: Display more descriptive messages here for the transaction errors: https://docs.mangopay.com/guide/errors
        // TODO: On some responses, shall we remove payment methods which are permanently failing? i.e. card becomes automatically inactive when failed for the first time it's tried.
        $commerce_payment->delete();

        drupal_set_message($rejected_error_message, 'error');
        \Drupal::logger('commerce_mangopay')->warning(sprintf('Pay In Failure (3DS): %s: %s', $payin->ResultCode, $payin->ResultMessage));
        return new RedirectResponse($failure_redirect_url);
        break;

      default:
        $commerce_payment->delete();

        \Drupal::logger('commerce_mangopay')->error(sprintf('Pay In Error (3DS): %s: %s', $payin->ResultCode, $payin->ResultMessage));
        drupal_set_message($unexpected_error_message, 'error');
        return new RedirectResponse($failure_redirect_url);
    }
  }
}
