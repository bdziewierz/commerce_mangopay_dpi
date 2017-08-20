<?php

namespace Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;

/**
 * Provides the interface for the commerce_mangopay payment gateway.
 */
interface MangopayInterface extends OffsitePaymentGatewayInterface, SupportsRefundsInterface, SupportsStoredPaymentMethodsInterface {

  /**
   * @return \MangoPay\MangoPayApi
   */
  public function getApi();

  /**
   * @param $first_name
   * @param $last_name
   * @param $email
   * @param $dob
   * @param $nationality
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
  public function createNaturalUser($first_name, $last_name, $email, $dob, $nationality, $country, $address_line1, $address_line2, $city, $postal_code, $occupation = '', $income_range = '', $tag = '');

  /**
   * @param $mangopay_api
   * @param $user_id
   * @param $currency_code
   * @param $description
   * @param string $tag
   * @return mixed
   */
  public function createWallet($user_id, $currency_code, $description, $tag = '');

  /**
   * @param $mangopay_api
   * @param $user_id
   * @param $currency_code
   * @param $card_type
   * @param string $tag
   * @return mixed
   */
  public function createCardRegistration($user_id, $currency_code, $card_type, $tag = '');

  /**
   * @param $user_id
   * @param $wallet_id
   * @param $card_id
   * @param $amount
   * @param $currency_code
   * @param $secure_mode_return_url
   * @return \MangoPay\PayIn
   */
  public function createDirectPayIn($user_id, $wallet_id, $card_id, $amount, $currency_code, $secure_mode_return_url);

  /**
   * @param $payin_id
   */
  public function getPayIn($payin_id);
}
