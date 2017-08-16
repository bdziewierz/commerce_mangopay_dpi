<?php

namespace Drupal\commerce_mangopay\PluginForm\Onsite;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Form\FormStateInterface;

class PaymentMethodAddForm extends BasePaymentMethodAddForm {

  /**
   * {@inheritdoc}
   */
  protected function buildCreditCardForm(array $element, FormStateInterface $form_state) {
    $element = parent::buildCreditCardForm($element, $form_state);

    // Default to a known valid test credit card number.
    $element['number']['#default_value'] = '4111111111111111';
    $element['number']['#commerce_mangopay_data'] = TRUE;
    $element['expiration']['month']['#commerce_mangopay_data'] = TRUE;
    $element['expiration']['year']['#commerce_mangopay_data'] = TRUE;
    $element['security_code']['#commerce_mangopay_data'] = TRUE;

    return $element;
  }

}
