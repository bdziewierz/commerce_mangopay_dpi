<?php

namespace Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
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
 *   payment_method_types = {"commerce_mangopay_credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class Onsite extends OnsitePaymentGatewayBase implements OnsiteInterface {

  /**
   * @var \MangoPay\MangoPayApi
   */
  protected $api;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);

    // Construct MANGOPAY Api object
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
    $this->api = new \MangoPay\MangoPayApi();
    $this->api->Config->BaseUrl = $base_url;
    $this->api->Config->ClientId = $client_id;
    $this->api->Config->ClientPassword = $client_pass;
    $this->api->Config->TemporaryFolder = file_directory_temp();
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

    $amount = doubleval($payment->getAmount()->getNumber()) * 100;
    $currency_code = $payment->getAmount()->getCurrencyCode();
    $card_id = $payment_method->getRemoteId();
    $user_id = $payment_method->user_id->value;
    $wallet_id = $payment_method->wallet_id->value;

    // TODO: Do we have to make a call here? Why not storing this?
    $card = $this->api->Cards->Get($card_id);

    // Create pay-in CARD DIRECT
    $pay_in = new \MangoPay\PayIn();
    $pay_in->CreditedWalletId = $wallet_id;
    $pay_in->AuthorId = $user_id;
    $pay_in->DebitedFunds = new \MangoPay\Money();
    $pay_in->DebitedFunds->Amount = $amount;
    $pay_in->DebitedFunds->Currency = $currency_code;
    $pay_in->Fees = new \MangoPay\Money();
    $pay_in->Fees->Amount = 0;
    $pay_in->Fees->Currency = $currency_code;

    // Payment type as CARD
    $pay_in->PaymentDetails = new \MangoPay\PayInPaymentDetailsCard();
    $pay_in->PaymentDetails->CardType = $card->CardType;
    $pay_in->PaymentDetails->CardId = $card->Id;

    // Execution type as DIRECT
    $pay_in->ExecutionDetails = new \MangoPay\PayInExecutionDetailsDirect();
    $pay_in->ExecutionDetails->SecureModeReturnURL = 'http://test.com';

    try {
      $result = $this->api->PayIns->Create($pay_in);
    } catch(\Exception $e) {
      ksm($e);
      throw new PaymentGatewayException('Unexpected error processing payment method');
    }

    switch($result->Status) {
      case \MangoPay\PayInStatus::Failed:
        ksm($result);

        // TODO: Handle various responses - https://docs.mangopay.com/guide/errors
        // 3DS
        // Soft decline
        // Hard decline
        // Etc.
        switch($result->ResultCode) {
          default:
            throw new DeclineException('Please try a different payment method');
        }
      break;

      // 3DS / Secure Mode, needs further processing
      case \MangoPay\PayInStatus::Created:
        ksm($result);
        
        
        throw new PaymentGatewayException('Secure mode not supported yet');
        break;

      // Success, mark payment as completed and continue
      case \MangoPay\PayInStatus::Succeeded:
        $payment->setState('completed');
        $payment->setRemoteId($result->Id);
        $payment->save();
        break;

      default:
        throw new PaymentGatewayException('Unexpected error processing payment method');
    }
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
      'card_type', 'card_alias', 'card_id', 'user_id', 'wallet_id', 'expiration'
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    // We use Mangopay's User Id and Wallet Id combination as Remote Customer Id
    $remote_customer_id = $payment_details['card_id'] . ':' . $payment_details['wallet_id'];
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $current_remote_customer_id = $this->getRemoteCustomerId($owner);
      if (empty($current_remote_customer_id) || $current_remote_customer_id != $remote_customer_id) {
        $this->setRemoteCustomerId($owner, $remote_customer_id);
        $owner->save();
      }
    }

    // Set relevant details on payment method object.
    $payment_method->user_id = $payment_details['user_id'];
    $payment_method->wallet_id = $payment_details['wallet_id'];
    $payment_method->card_type = $payment_details['card_type'];
    $payment_method->card_number = $payment_details['card_alias'];
    $payment_method->card_exp_month = $payment_details['expiration']['month'];
    $payment_method->card_exp_year = $payment_details['expiration']['year'];
    $payment_method->setRemoteId($payment_details['card_id']);
    $payment_method->setExpiresTime(CreditCard::calculateExpirationTimestamp($payment_details['expiration']['month'], $payment_details['expiration']['year']));
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

    // TODO: Instruct MANGOPAY API to remove credit card.
  }

  /**
   * 
   * @return \MangoPay\MangoPayApi
   */
  public function getApi() {
    return $this->api;
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
  public function createNaturalUser($first_name, $last_name, $dob, $email, $country, $address_line1, $address_line2, $city, $postal_code, $occupation = '', $income_range = '', $tag = '') {
    $user = new \MangoPay\UserNatural();
    $user->FirstName = $first_name;
    $user->LastName = $last_name;
    $user->Email = $email;
    $user->CountryOfResidence = $country;
    $user->Nationality = $country;
    $user->Birthday = $dob;
    $user->Occupation = $occupation;
    $user->IncomeRange = $income_range;
    $user->Address = new \MangoPay\Address();
    $user->Address->AddressLine1 = $address_line1;
    $user->Address->AddressLine2 = $address_line2;
    $user->Address->City = $city;
    $user->Address->PostalCode = $postal_code;
    $user->Address->Country = $country;
    $user->Tag = $tag;
    return $this->api->Users->Create($user);
  }

  /**
   * @param $mangopay_api
   * @param $user_id
   * @param $currency_code
   * @param $description
   * @param string $tag
   * @return mixed
   */
  public function createWallet($user_id, $currency_code, $description, $tag = '') {
    $wallet = new \MangoPay\Wallet();
    $wallet->Owners = [$user_id];
    $wallet->Description = $description;
    $wallet->Currency = $currency_code;
    $wallet->Tag = $tag;
    return $this->api->Wallets->Create($wallet);
  }

  /**
   * @param $mangopay_api
   * @param $user_id
   * @param $currency_code
   * @param $card_type
   * @param string $tag
   * @return mixed
   */
  public function createCardRegistration($user_id, $currency_code, $card_type, $tag = '') {
    $cardRegister = new \MangoPay\CardRegistration();
    $cardRegister->UserId = $user_id;
    $cardRegister->Currency = $currency_code;
    $cardRegister->CardType = $card_type;
    $cardRegister->Tag = $tag;
    return $this->api->CardRegistrations->Create($cardRegister);
  }
}
