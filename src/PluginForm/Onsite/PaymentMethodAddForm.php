<?php

namespace Drupal\commerce_mangopay\PluginForm\Onsite;

use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;
use Drupal\Core\Form\FormStateInterface;
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
    switch($mode) {
      case 'production':
        $base_url = 'https://api.mangopay.com';
        break;
      default:
        $base_url = 'https://api.sandbox.mangopay.com';
        break;
    }

    // Attach JS script and related settings.
    $form['#attached']['library'][] = 'commerce_payment/payment_method_form';
    $form['#attached']['library'][] = 'commerce_mangopay/register_card';
    $form['#attached']['drupalSettings']['commerceMangopay'] = [
      'mode' => $mode,
      'baseUrl' => $base_url,
      'clientId' => $client_id,
      'cardType' => 'CB_VISA_MASTERCARD', // TODO: Get from config?!
      'paymentGatewayId' => $payment_gateway_id,
    ];

    $form['#tree'] = TRUE;
    $form['payment_details'] = [
      '#parents' => array_merge($form['#parents'], ['payment_details']),
      '#type' => 'container',
      '#payment_method_type' => $payment_method->bundle(),
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
    // Placeholder for the detected card type. Set by validateCreditCardForm().
    $form['payment_details']['type'] = [
      '#type' => 'hidden',
      '#value' => '',
    ];
    $form['payment_details']['number'] = [
      '#type' => 'textfield',
      '#title' => t('Card number'),
      '#attributes' => ['autocomplete' => 'off'],
      '#required' => FALSE,
      '#maxlength' => 19,
      '#size' => 20,
      '#commerce_mangopay_sensitive' => TRUE // Mark as sensitive - Can only be transferred to MANGOPAY directly
    ];
    $form['payment_details']['expiration'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['credit-card-form__expiration'],
      ],
    ];
    $form['payment_details']['expiration']['month'] = [
      '#type' => 'select',
      '#title' => t('Month'),
      '#options' => $months,
      '#default_value' => date('m'),
      '#required' => TRUE
    ];
    $form['payment_details']['expiration']['divider'] = [
      '#type' => 'item',
      '#title' => '',
      '#markup' => '<span class="credit-card-form__divider">/</span>',
    ];
    $form['payment_details']['expiration']['year'] = [
      '#type' => 'select',
      '#title' => t('Year'),
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
      '#required' => FALSE,
      '#commerce_mangopay_sensitive' => TRUE // Mark as sensitive - Can only be transferred to MANGOPAY directly
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

    /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
    $billing_profile = Profile::create([
      'type' => 'customer',
      'uid' => $payment_method->getOwnerId(),
    ]);
    if ($order = $this->routeMatch->getParameter('commerce_order')) {
      $store = $order->getStore();
    }
    else {
      /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
      $store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
      $store = $store_storage->loadDefault();
    }

    $form['billing_information'] = [
      '#parents' => array_merge($form['#parents'], ['billing_information']),
      '#type' => 'commerce_profile_select',
      '#default_value' => $billing_profile,
      '#default_country' => $store ? $store->getAddress()->getCountryCode() : NULL,
      '#available_countries' => $store ? $store->getBillingCountries() : [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    // TODO: Validate we've got everything we need passed from add method
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
