<?php

namespace Drupal\commerce_mangopay\PluginForm\OffsiteRedirect;

use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;

class PaymentOffsiteForm extends PaymentGatewayFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();

    // Attach payment method information.
    // It appears \PaymentProcess pane is doing it only for OnSite payments
    // while we've got a little bit of OnSite / OffSite hybrid here.
    $payment->payment_method = $order->payment_method->entity;

    // Make sure payment is saved before proceeding further.
    // We will need this object in the pay-in callback.
    // Pay in callback is responsible of clearing it in case if something goes wrong.
    $payment->save();

    $form['anchor'] = [
      '#type' => 'container',
      '#tag' => 'div'
    ];

    $form['#attached']['library'][] = 'commerce_mangopay/pay_in';
    $form['#attached']['drupalSettings']['commerceMangopay'] = [
      'paymentId' => $payment->id(),
      'returnUrl' => $form['#return_url'],
      'cancelUrl' => $form['#cancel_url'],
      'exceptionUrl' => $form['#exception_url'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Nothing here. This form is never submitted to Drupal.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Nothing here. This form is never submitted to Drupal.
  }

}
