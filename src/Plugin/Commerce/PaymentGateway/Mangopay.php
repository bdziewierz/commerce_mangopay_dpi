<?php

namespace Drupal\commerce_mangopay_dpi\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Off-site Secure Mode (3DS) redirect
 *
 * @CommercePaymentGateway(
 *   id = "commerce_mangopay_dpi",
 *   label = "MANGOPAY",
 *   display_label = "MANGOPAY",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_mangopay_dpi\PluginForm\Onsite\PaymentMethodAddForm",
 *     "offsite-payment" = "Drupal\commerce_mangopay_dpi\PluginForm\OffsiteRedirect\PaymentOffsiteForm",
 *   },
 *   modes = {"sandbox" = "Sandbox", "production" = "Production"},
 *   payment_method_types = {"commerce_mangopay_dpi_credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
 *   },
 * )
 */
class Mangopay extends OffsitePaymentGatewayBase implements MangopayInterface {
  const STANDARD_TAG = 'drupal commerce';

  /**
   * @var \MangoPay\MangoPayApi
   */
  protected $api;
  
  /**
   *
   * @return \MangoPay\MangoPayApi
   */
  public function getApi() {
    return $this->api;
  }

  /**
   * @return mixed
   */
  public function getTag() {
    return $this->getConfiguration()['tag'];
  }

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
      'simple_kyc' => FALSE,
      'tag' => 'commerce_mangopay_dpi',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    // The PaymentInformation pane uses payment method labels
    // for on-site gateways, the display label is unused.
    $form['display_label']['#access'] = FALSE;

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
      '#default_value' => $this->configuration['client_pass']
    ];

    $form['simple_kyc'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Simplified KYC'),
      '#description' => $this->t('Simplified Know Your Customer information gathering for marketplaces.'),
      '#default_value' => $this->configuration['simple_kyc'],
    ];

    $form['tag'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tag'),
      '#description' => $this->t('Standard tag to mark all MANGOPAY resources with. Used to identify resources \'owned\' by this payment gateway. Please note that once set, it\'s not recommended to change this value.'),
      '#default_value' => $this->configuration['tag'],
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
      if (!empty($values['client_pass'])) {
        $this->configuration['client_pass'] = $values['client_pass'];
      }
      $this->configuration['simple_kyc'] = $values['simple_kyc'];
      $this->configuration['tag'] = $values['tag'];
    }
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

    // Set remote User Id on the user object.
    $owner = $payment_method->getOwner();
    if ($owner && $owner->isAuthenticated()) {
      $this->setRemoteCustomerId($owner, $payment_details['user_id']);
      $owner->save();
    }

    // Set relevant details on payment method object.
    $payment_method->user_id = $payment_details['user_id'];
    $payment_method->wallet_id = $payment_details['wallet_id'];
    $payment_method->card_type = $payment_details['card_type'];
    $payment_method->card_number = $payment_details['card_alias'];
    $payment_method->card_exp_month = $payment_details['expiration']['month'];
    $payment_method->card_exp_year = $payment_details['expiration']['year'];
    $payment_method->currency_code = $payment_details['currency_code'];
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

    // TODO: Instruct MANGOPAY API to remove the credit card? Is this possible?
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $payment_id = $request->get('payment_id');
    if (empty($payment_id)) {
      throw new PaymentGatewayException('Payment Id not passed from the gateway.');
    }

    // Check if any of the payments associated with the order matches the
    // payment id passed from the gateway and confirm that it's completed.
    $payments = \Drupal::entityTypeManager()->getStorage('commerce_payment')->loadMultipleByOrder($order);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    foreach($payments as $payment) {
      if ($payment->getRemoteId() == $payment_id && $payment->getState()->getName() == 'completed') {
        return;
      }
    }

    // If not valid payments found, throw an exception.
    throw new PaymentGatewayException('Payment has not been processed correctly.');
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    drupal_set_message($this->t('You have canceled checkout but may resume the checkout process here when you are ready.'));
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {}

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
  public function createNaturalUser($first_name, $last_name, $email, $dob, $nationality, $country, $address_line1, $address_line2, $city, $postal_code, $occupation = '', $income_range = '', $tag = '') {
    $user = new \MangoPay\UserNatural();
    $user->FirstName = $first_name;
    $user->LastName = $last_name;
    $user->Email = $email;
    $user->CountryOfResidence = $country;
    $user->Nationality = $nationality;
    $user->Birthday = (int)$dob;
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
   * {@inheritdoc}
   */
  public function getUser($user_id) {
    return $this->api->Users->Get($user_id);
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getWallets($user_id) {
    return $this->api->Users->GetWallets($user_id);
  }

  /**
   * {@inheritdoc}
   */
  public function createCardRegistration($user_id, $currency_code, $card_type, $tag = '') {
    $cardRegister = new \MangoPay\CardRegistration();
    $cardRegister->UserId = $user_id;
    $cardRegister->Currency = $currency_code;
    $cardRegister->CardType = $card_type;
    $cardRegister->Tag = $tag;
    return $this->api->CardRegistrations->Create($cardRegister);
  }

  /**
   * {@inheritdoc}
   */
  public function createDirectPayIn($user_id, $wallet_id, $card_id, $amount, $currency_code, $secure_mode_return_url) {

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
    // TODO: Do we have to make a call here? Why not storing this?
    // TODO: Shall we validate in case card no longer exists or is expired?
    $card = $this->api->Cards->Get($card_id);

    $pay_in->PaymentDetails = new \MangoPay\PayInPaymentDetailsCard();
    $pay_in->PaymentDetails->CardType = $card->CardType;
    $pay_in->PaymentDetails->CardId = $card->Id;

    // Execution type as DIRECT
    $pay_in->ExecutionDetails = new \MangoPay\PayInExecutionDetailsDirect();
    $pay_in->ExecutionDetails->SecureModeReturnURL = $secure_mode_return_url;

    return $this->api->PayIns->Create($pay_in);
  }

  /**
   * {@inheritdoc}
   */
  public function getPayIn($payin_id) {
    return $this->api->PayIns->Get($payin_id);
  }


}
