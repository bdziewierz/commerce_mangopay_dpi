<?php

namespace Drupal\commerce_mangopay\PluginForm\Onsite;

use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManager;
use Drupal\profile\Entity\Profile;

class PaymentMethodAddForm extends PaymentGatewayFormBase {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new PaymentMethodAddForm.
   */
  public function __construct() {
    $this->routeMatch = \Drupal::service('current_route_match');
  }

  /**
   * {@inheritdoc}
   */
  public function getErrorElement(array $form, FormStateInterface $form_state) {
    return $form['payment_details'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $payment_method = $this->entity;
    $payment_gateway_id = $payment_method->getPaymentGatewayId();

    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment_method->getPaymentGateway()->getPlugin();
    $mode = $payment_gateway_plugin->getConfiguration()['mode'];
    $client_id = $payment_gateway_plugin->getConfiguration()['client_id'];
    $card_type = 'CB_VISA_MASTERCARD'; // TODO: Are those types only ones supported at the moment?
    switch($mode) {
      case 'production':
        $base_url = 'https://api.mangopay.com';
        break;
      default:
        $base_url = 'https://api.sandbox.mangopay.com';
        break;
    }

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    if ($order = $this->routeMatch->getParameter('commerce_order')) {
      $store = $order->getStore();
      $currency_code = $order->getTotalPrice()->getCurrencyCode();
    }
    else {
      /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
      $store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
      $store = $store_storage->loadDefault();
      $currency_code = $store->getDefaultCurrencyCode();
    }

    $email = NULL;
    $user = \Drupal::currentUser();
    if ($user->isAuthenticated()) {
      $email = $user->getEmail();
    }

    // Attach JS script and related settings.
    $form['#attached']['library'][] = 'commerce_payment/payment_method_form';
    $form['#attached']['library'][] = 'commerce_mangopay/register_card';
    $form['#attached']['drupalSettings']['commerceMangopay'] = [
      'mode' => $mode,
      'baseUrl' => $base_url,
      'clientId' => $client_id,
      'email' => $email,
      'cardType' => $card_type,
      'currencyCode' => $currency_code,
      'paymentGatewayId' => $payment_gateway_id,
    ];

    // Attach container for displaying status and error messages with javascript.
    // Waiting patiently for: https://www.drupal.org/node/77245 but in the meantime...
    $form['status'] = [
      '#theme' => 'status_messages',
      '#display' => 'error',

      // We take an opportunity here to warn the user that this form requires
      // the JavaScript to be enabled (sorry, no progressive enhancement at the mo).
      // If a user has JS enabled, this message will disappear.
      // Doing this also renders a full messages block with actual list items
      // usable from JS level to display errors.
      '#message_list' => ['error' => [
        t('This form requires JavaScript.'),
        t('Please make sure your browser is up to date and JavaScript is not disabled.')]],
      '#status_headings' => ['error' => t('Error message')]];

    $form['hide_status'] = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#value' => 'document.getElementsByClassName("js-messages")[0].style.display = "none"',
    ];

    $form['#tree'] = TRUE;
    $form['payment_details'] = [
      '#parents' => array_merge($form['#parents'], ['payment_details']),
      '#type' => 'container',
      '#payment_method_type' => $payment_method->bundle()
    ];

    // Build a month select list that shows months with a leading zero.
    $months = [];
    for ($i = 1; $i < 13; $i++) {
      $month = str_pad($i, 2, '0', STR_PAD_LEFT);
      $months[$month] = $month;
    }
    // Build a year select list that uses a 4 digit key with a 2 digit value.
    $current_year = date('y');
    $years = [];
    for ($i = 0; $i < 10; $i++) {
      $years[$current_year + $i] = $current_year + $i;
    }

    $form['payment_details']['#attributes']['class'][] = 'credit-card-form';

    $form['payment_details']['number'] = [
      '#type' => 'textfield',
      '#title' => t('Card number'),
      '#attributes' => ['autocomplete' => 'off'],
      '#maxlength' => 19,
      '#size' => 20,
      '#required' => FALSE, // From the perspective of FAPI this field is not required. We only use it in JavaScript.
      /**
       * Mark as sensitive - Can only be transferred to MANGOPAY directly
       * @see commerce_mangopay_preprocess_input
       * @see commerce_mangopay_preprocess_form_element
       */
      '#commerce_mangopay_sensitive' => TRUE
    ];

    $form['payment_details']['expiration'] = [
      '#type' => 'item',
      '#title' => t('Expiry date'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['credit-card-form__expiration'],
      ],
    ];

    $form['payment_details']['expiration']['month'] = [
      '#type' => 'select',
      '#options' => $months,
      '#default_value' => date('m'),
      '#required' => TRUE
    ];


    $form['payment_details']['expiration']['year'] = [
      '#type' => 'select',
      '#options' => $years,
      '#default_value' => $current_year,
      '#required' => TRUE
    ];

    $form['payment_details']['security_code'] = [
      '#type' => 'textfield',
      '#title' => t('CVV'),
      '#attributes' => ['autocomplete' => 'off'],
      '#maxlength' => 4,
      '#size' => 4,
      '#placeholder' => t('Three digit number on the back of your card'),
      '#description' => t('Three digit number on the back of your card'),
      '#required' => FALSE, // From the perspective of FAPI this field is not required. We only use it in JavaScript.
      /**
       * Mark as sensitive - Can only be transferred to MANGOPAY directly
       * @see commerce_mangopay_preprocess_input
       * @see commerce_mangopay_preprocess_form_element
       */
      '#commerce_mangopay_sensitive' => TRUE
    ];

    $form['payment_details']['currency_code'] = [
      '#type' => 'hidden',
      '#default_value' => $currency_code
    ];

    $form['payment_details']['card_type'] = [
      '#type' => 'hidden'
    ];

    $form['payment_details']['card_alias'] = [
      '#type' => 'hidden'
    ];

    $form['payment_details']['card_id'] = [
      '#type' => 'hidden'
    ];

    $form['payment_details']['user_id'] = [
      '#type' => 'hidden'
    ];

    $form['payment_details']['wallet_id'] = [
      '#type' => 'hidden'
    ];

    $form['kyc_details'] = [
      '#parents' => array_merge($form['#parents']),
      '#type' => 'container',
      '#payment_method_type' => $payment_method->bundle()
    ];

    if ($payment_gateway_plugin->getConfiguration()['simple_kyc']) {
      $form['kyc_details']['dob'] = [
        '#type' => 'hidden',
        '#default_value' => '1970-01-01', // For simplified KYC we always hardcode a default value. It's required, but ignored by MANGOPAY
      ];

      $form['kyc_details']['nationality'] = [
        '#type' => 'hidden',
        '#default_value' => 'FR', // For simplified KYC we always hardcode a default value. It's required, but ignored by MANGOPAY. Vive la France!
      ];
    }
    else {
      $form['kyc_details']['disclaimer'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => t('Our payment service provider MANGOPAY SA, which is a EU Licensed and Regulated Financial Institution, requires us to capture our customers\' nationality and date of birth for compliance reasons. This data is passed on to the financial institution and not stored nor used by us for any business activities.'),
      ];

      $form['kyc_details']['dob'] = [
        '#type' => 'date',
        '#title' => t('Date of birth'),
        '#required' => TRUE,
      ];

      $countries = CountryManager::getStandardList();
      $form['kyc_details']['nationality'] = [
        '#type' => 'select',
        '#title' => t('Nationality'),
        '#required' => TRUE,
        '#options' => $countries,
      ];
    }

    /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
    $billing_profile = Profile::create([
      'type' => 'customer',
      'uid' => $payment_method->getOwnerId(),
    ]);

    $form['billing_information'] = [
      '#parents' => array_merge($form['#parents'], ['billing_information']),
      '#type' => 'commerce_profile_select',
      '#default_value' => $billing_profile,
      '#default_country' => $store ? $store->getAddress()->getCountryCode() : NULL,
      '#available_countries' => $store ? $store->getBillingCountries() : [],
    ];

    $form['mangopay_badge'] = [
      '#prefix' => '<div>',
      '#suffix' => '</div>',
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => [
        'class' => ['powered-by-mangopay'],
        'style' => 'width: 100%',
        'src' => '/' . drupal_get_path('module', 'commerce_mangopay') . '/images/powered-by-mangopay.png']
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    $payment_details = $values['payment_details'];

    // Validate if we have required data to correctly register the card.
    if (empty($payment_details['card_type']) || empty($payment_details['card_alias'])
      || empty($payment_details['card_id']) || empty($payment_details['user_id'])
      || empty($payment_details['wallet_id'])) {
      $form_state->setError($form,  t('The credit card form has not been processed correctly. Please make sure your browser is up to date and supports JavaScript.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;
    $payment_method->setBillingProfile($form['billing_information']['#profile']);

    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;

    $values = $form_state->getValue($form['#parents']);

    try {
      $payment_gateway_plugin->createPaymentMethod($payment_method, $values['payment_details']);
    }
    catch (DeclineException $e) {
      \Drupal::logger('commerce_payment')->warning($e->getMessage());
      throw new DeclineException('We encountered an error processing your payment method. Please verify your details and try again.');
    }
    catch (PaymentGatewayException $e) {
      \Drupal::logger('commerce_payment')->error($e->getMessage());
      throw new PaymentGatewayException('We encountered an unexpected error processing your payment method. Please try again later.');
    }
  }
}
