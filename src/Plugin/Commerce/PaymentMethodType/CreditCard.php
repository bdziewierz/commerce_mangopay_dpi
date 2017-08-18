<?php

namespace Drupal\commerce_mangopay\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce\BundleFieldDefinition;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType;

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

    return $fields;
  }

}
