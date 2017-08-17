<?php

namespace Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the On-site payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "commerce_mangopay_onsite",
 *   label = "MANGOPAY (On-site)",
 *   display_label = "MANGOPAY",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_mangopay\PluginForm\Onsite\PaymentMethodAddForm",
 *   },
 *   modes = {"sandbox" = "Sandbox", "production" = "Production"},
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class Onsite extends OnsitePaymentGatewayBase implements OnsiteInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    // You can create an instance of the SDK here and assign it to $this->api.
    // Or inject Guzzle when there's no suitable SDK.
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'client_id' => '',
      'client_pass' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Id'),
      '#description' => $this->t('Please enter your MANGOPAY client id applicable to the environment (mode) you\'re using.'),
      '#default_value' => $this->configuration['client_id'],
      '#required' => TRUE,
    ];

    $form['client_pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Client Password'),
      '#description' => $this->t('Please enter your MANGOPAY client password applicable to the environment (mode) you\'re using.'),
      '#default_value' => $this->configuration['client_pass'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['client_id'] = $values['client_id'];
      $this->configuration['client_pass'] = $values['client_pass'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    // Add a built in test for testing decline exceptions.
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $billing_address */
    if ($billing_address = $payment_method->getBillingProfile()) {
      $billing_address = $payment_method->getBillingProfile()->get('address')->first();
      if ($billing_address->getPostalCode() == '53140') {
        throw new HardDeclineException('The payment was declined');
      }
    }

    // Perform the create payment request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Remember to take into account $capture when performing the request.
    $amount = $payment->getAmount();
    $payment_method_token = $payment_method->getRemoteId();
    // The remote ID returned by the request.
    $remote_id = '123456';
    $next_state = $capture ? 'completed' : 'authorization';

    $payment->setState($next_state);
    $payment->setRemoteId($remote_id);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    // Perform the capture request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    $remote_id = $payment->getRemoteId();
    $number = $amount->getNumber();

    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);
    // Perform the void request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    $remote_id = $payment->getRemoteId();

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    // Perform the refund request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    $remote_id = $payment->getRemoteId();
    $number = $amount->getNumber();

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      // The expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. They are expected to be valid.
      'type', 'number', 'expiration',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    // If the remote API needs a remote customer to be created.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $customer_id = $this->getRemoteCustomerId($owner);
      // If $customer_id is empty, create the customer remotely and then do
      // $this->setRemoteCustomerId($owner, $customer_id);
      // $owner->save();
    }

    // Perform the create request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // You might need to do different API requests based on whether the
    // payment method is reusable: $payment_method->isReusable().
    // Non-reusable payment methods usually have an expiration timestamp.
    $payment_method->card_type = $payment_details['type'];
    // Only the last 4 numbers are safe to store.
    $payment_method->card_number = substr($payment_details['number'], -4);
    $payment_method->card_exp_month = $payment_details['expiration']['month'];
    $payment_method->card_exp_year = $payment_details['expiration']['year'];
    $expires = CreditCard::calculateExpirationTimestamp($payment_details['expiration']['month'], $payment_details['expiration']['year']);
    // The remote ID returned by the request.
    $remote_id = '789';

    $payment_method->setRemoteId($remote_id);
    $payment_method->setExpiresTime($expires);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the remote record here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Delete the local entity.
    $payment_method->delete();
  }

  /**
   *
   */
  public function createMangopayApi() {
    // Retrieve configuration of the payment gateway
    $mode = $this->getConfiguration()['mode'];
    $client_id = $this->getConfiguration()['client_id'];
    $client_pass = $this->getConfiguration()['client_pass'];
    switch($mode) {
      case 'production':
        $base_url = 'https://api.mangopay.com';
        break;
      default:
        $base_url = 'https://api.sandbox.mangopay.com';
        break;
    }

    // Create instance of MangoPayApi SDK
    $mangopay_api = new \MangoPay\MangoPayApi();
    $mangopay_api->Config->BaseUrl = $base_url;
    $mangopay_api->Config->ClientId = $client_id;
    $mangopay_api->Config->ClientPassword = $client_pass;
    $mangopay_api->Config->TemporaryFolder = file_directory_temp();

    return $mangopay_api;
  }

  /**
   * @param $mangopay_api
   * @param $first_name
   * @param $last_name
   * @param $dob
   * @param $email
   * @param $country
   * @param $address_line1
   * @param $address_line2
   * @param $city
   * @param $postal_code
   * @param string $occupation
   * @param string $income_range
   * @param string $tag
   * @return mixed
   */
  public function createNaturalUser($mangopay_api, $first_name, $last_name, $dob, $email, $country, $address_line1, $address_line2, $city, $postal_code, $occupation = '', $income_range = '', $tag = '') {
    $user = new \MangoPay\UserNatural();
    $user->FirstName = $first_name;
    $user->LastName = $last_name;
    $user->Email = $email;
    $user->CountryOfResidence = $country;
    $user->Nationality = $country; // TODO: Fix me - how to get real country of residence?
    $user->Birthday = $dob; // TODO: Fix me - how to get real birthday?
    $user->Occupation = $occupation;
    $user->IncomeRange = $income_range;
    $user->Address = new \MangoPay\Address();
    $user->Address->AddressLine1 = $address_line1;
    $user->Address->AddressLine2 = $address_line2;
    $user->Address->City = $city;
    $user->Address->PostalCode = $postal_code;
    $user->Address->Country = $country;
    $user->Tag = $tag;
    return $mangopay_api->Users->Create($user);
  }

  /**
   * @param $mangopay_api
   * @param $user_id
   * @param $currency_code
   * @param $description
   * @param string $tag
   * @return mixed
   */
  public function createWallet($mangopay_api, $user_id, $currency_code, $description, $tag = '') {
    $wallet = new \MangoPay\Wallet();
    $wallet->Owners = [$user_id];
    $wallet->Description = $description;
    $wallet->Currency = $currency_code;
    $wallet->Tag = $tag;
    return $mangopay_api->Wallets->Create($wallet);
  }

  /**
   * @param $mangopay_api
   * @param $user_id
   * @param $currency_code
   * @param $card_type
   * @param string $tag
   * @return mixed
   */
  public function createCardRegistration($mangopay_api, $user_id, $currency_code, $card_type, $tag = '') {
    $cardRegister = new \MangoPay\CardRegistration();
    $cardRegister->UserId = $user_id;
    $cardRegister->Currency = $currency_code;
    $cardRegister->CardType = $card_type;
    $cardRegister->Tag = $tag;
    return $mangopay_api->CardRegistrations->Create($cardRegister);
  }
}
