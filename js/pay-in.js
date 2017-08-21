(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.commerceMangopayPayIn = {
    attach: function (context, settings) {
      // Capture submit button. We want to do things with MANGOPAY card registration js kit before submitting to Drupal.
      $('div[data-drupal-selector="edit-payment-process-offsite-payment-payin"]:not(".js-processed")', context).addClass('js-processed').each(function() {
        $.ajax({
            method: "POST",
            url: "/commerce-mangopay/pay-in/" + drupalSettings.commerceMangopay.paymentId,
            data: {}
          })
          .done(function(payInResponse) {
            switch(payInResponse.status) {
              case 'Succeeded':
                // In case of successful payment, We redirect to the return URL directly.
                window.location.replace(drupalSettings.commerceMangopay.returnUrl + "?payment_id=" + payInResponse.paymentId);
                break;
              case 'Created':
                // In case of created response with 3DS return URL, We redirect to 3DS URL directly.
                window.location.replace(payInResponse.secureModeUrl);
                break;
              case 'Failed':
              case 'Critical':
                // In case of failed payment, We redirect to exception URL.
                window.location.replace(drupalSettings.commerceMangopay.exceptionUrl);
                break;

            }
          })
          .fail(function(payInError) {
            window.location.replace(drupalSettings.commerceMangopay.exceptionUrl);
          });
      });

    }
  };

})(jQuery, Drupal, drupalSettings);