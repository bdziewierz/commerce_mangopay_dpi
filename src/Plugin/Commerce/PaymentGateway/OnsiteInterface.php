<?php

namespace Drupal\commerce_mangopay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;

/**
 * Provides the interface for the commerce_mangopay payment gateway.
 *
 * The OnsitePaymentGatewayInterface is the base interface which all on-site
 * gateways implement. The other interfaces signal which additional capabilities
 * the gateway has. The gateway plugin is free to expose additional methods,
 * which would be defined below.
 */
interface OnsiteInterface extends OnsitePaymentGatewayInterface, SupportsRefundsInterface {

  /**
   * @return \MangoPay\MangoPayApi
   */
  public function getApi();

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
  public function createNaturalUser($first_name, $last_name, $dob, $email, $country, $address_line1, $address_line2, $city, $postal_code, $occupation = '', $income_range = '', $tag = '');

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
}
