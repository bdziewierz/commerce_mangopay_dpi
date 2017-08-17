(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.commerceMangopay = {};

  /**
   *
   * @returns {{cardNumber: *, cardExpirationDate: *, cardCvx: *, cardType: (*|string|string)}}
   */
  Drupal.commerceMangopay.getCardInput = function() {
    return {
      cardNumber: $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-number"]').val(),
      cardExpirationDate: $('select[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-expiration-month"]').val()
      + $('select[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-expiration-year"]').val(),
      cardCvx: $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-security-code"]').val(),
      cardType: drupalSettings.commerceMangopay.cardType
    };
  };

  /**
   *
   * @returns {{firstName: *, lastName: *, addressLine1: *, addressLine2: *, postalCode: *, city: *, country: *, email: string, currencyCode: string}}
   */
  Drupal.commerceMangopay.getBillingInformationInput = function() {
    return {
      firstName: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-given-name"]').val(),
      lastName: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-family-name"]').val(),
      addressLine1: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-address-line1"]').val(),
      addressLine2: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-address-line2"]').val(),
      postalCode: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-postal-code"]').val(),
      city: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-locality').val(),
      country: $('select[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-country-code"]').val(),
      email: 'jan@example.com' // TODO: Fetch this
    };
  };

  Drupal.behaviors.commerceMangopay = {
    attach: function (context, settings) {

      // Register baseURL and client ID with MANGOPAY object.
      mangoPay.cardRegistration.baseURL = drupalSettings.commerceMangopay.baseUrl;
      mangoPay.cardRegistration.clientId = drupalSettings.commerceMangopay.clientId;

      // Capture submit button. We want to do things with MANGOPAY card registration js kit before submitting to Drupal.
      $('button[data-drupal-selector="edit-actions-next"]:not(".ajax-processed")', context).addClass('ajax-processed').click(function(event) {
        // TODO: Validate if we have card data present and if it's the right format.
        var submitButton = $(this);

        // Prevent multiple clicks
        submitButton.attr("disabled", true);

        // First, get user data from MANGOPAY API.
        var currencyCode = "USD"; // TODO: Fetch it from the cart?
        var billingInformationInput = Drupal.commerceMangopay.getBillingInformationInput();
        $.ajax({
            method: "POST",
            url: "/commerce-mangopay/get-user/" + drupalSettings.commerceMangopay.paymentGatewayId,
            data: {
              card_type: drupalSettings.commerceMangopay.cardType,
              currency_code: currencyCode,
              first_name: billingInformationInput.firstName,
              last_name: billingInformationInput.lastName,
              email: billingInformationInput.email,
              address_line1: billingInformationInput.addressLine1,
              address_line2: billingInformationInput.addressLine2,
              postal_code: billingInformationInput.postalCode,
              city: billingInformationInput.city,
              country: billingInformationInput.country
            }
          })
          .done(function(getUserResponse) {
            // Secondly, initialize card registration object with get-user provided data
            mangoPay.cardRegistration.init({
              cardRegistrationURL : getUserResponse.cardRegistrationURL,
              preregistrationData : getUserResponse.preregistrationData,
              accessKey : getUserResponse.accessKey,
              Id : getUserResponse.cardRegistrationId
            });

            // Finally, collect sensitive card data from the form and register the card
            var cardInput = Drupal.commerceMangopay.getCardInput();
            mangoPay.cardRegistration.registerCard(cardInput,
              function(registerCardResponse) {
                // Save relevant data in the hidden fields o nthe form and pass the form over to Drupal for processing.
                $('input[data-drupal-selector="edit-payment-information-add-payment-method-card-id"]')
                  .val(registerCardResponse.CardId);
                $('input[data-drupal-selector="edit-payment-information-add-payment-method-user-id"]')
                  .val(getUserResponse.userId);
                $('input[data-drupal-selector="edit-payment-information-add-payment-method-wallet-id"]')
                  .val(getUserResponse.walletId);

                // Submit the whole and pass on control to actual Commerce checkout routines.
                // submitButton.parents('form').first().submit();
                console.log(registerCardResponse);
              },
              function(errorResponse){
                // TODO: Handle error
                console.log(errorResponse)
              }
            );
          })
          .fail(function() {
            // TODO: Handle error
            console.log("Error")
          })
          .always(function() {
            submitButton.removeAttr("disabled");
          });

        // Initialize with card register data prepared on the server


        event.preventDefault();
      });

    }
  };

})(jQuery, Drupal, drupalSettings);