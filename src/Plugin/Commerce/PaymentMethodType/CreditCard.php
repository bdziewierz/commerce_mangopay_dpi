<?php

namespace Drupal\commerce_mangopay_dpi\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce\BundleFieldDefinition;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType;
use Drupal\commerce_payment\CreditCard as CreditCardHelper;

/**
 * Provides the credit card payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "commerce_mangopay_credit_card",
 *   label = @Translation("Credit card"),
 *   create_label = @Translation("New credit card"),
 * )
 */
class CreditCard extends \Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\CreditCard {
  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $card_type = CreditCardHelper::getType($payment_method->card_type->value);
    $args = [
      '@card_type' => $card_type->getLabel(),
      '@card_number' => $payment_method->card_number->value,
      '@currency_code' => $payment_method->currency_code->value,
    ];
    return $this->t('@card_type ending in @card_number (@currency_code)', $args);
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['user_id'] = BundleFieldDefinition::create('string')
      ->setLabel(t('MANGOPAY User Id'))
      ->setDescription(t('Remote User Id for MANGOPAY API'))
      ->setRequired(TRUE);

    $fields['wallet_id'] = BundleFieldDefinition::create('string')
      ->setLabel(t('MANGOPAY Wallet Id'))
      ->setDescription(t('Remote Wallet Id for MANGOPAY API'))
      ->setRequired(TRUE);

    $fields['currency_code'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Card currency code'))
      ->setDescription(t('Currency code for this card'))
      ->setRequired(TRUE);

    return $fields;
  }

}
