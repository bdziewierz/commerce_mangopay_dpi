(function ($, Drupal, drupalSettings) {
  'use strict';

  var VISA = 'visa';
  var MASTERCARD = 'mastercard';
  var AMERICAN_EXPRESS = 'amex';
  var DINERS_CLUB = 'dinersclub';
  var DISCOVER = 'discover';
  var JCB = 'jcb';
  var UNIONPAY = 'unionpay';
  var MAESTRO = 'maestro';

  var CVV = 'CVV';
  var CID = 'CID';
  var CVC = 'CVC';
  var CVN = 'CVN';

  Drupal.commerceMangopay = {};

  Drupal.commerceMangopay.cardTypes = {};

  Drupal.commerceMangopay.cardTypes[VISA] = {
    niceType: 'Visa',
    type: VISA,
    prefixPattern: /^4$/,
    exactPattern: /^4\d*$/,
    gaps: [4, 8, 12],
    lengths: [16, 18, 19],
    code: {
      name: CVV,
      size: 3
    }
  };

  Drupal.commerceMangopay.cardTypes[MASTERCARD] = {
    niceType: 'MasterCard',
    type: MASTERCARD,
    prefixPattern: /^(5|5[1-5]|2|22|222|222[1-9]|2[3-6]|27|27[0-2]|2720)$/,
    exactPattern: /^(5[1-5]|222[1-9]|2[3-6]|27[0-1]|2720)\d*$/,
    gaps: [4, 8, 12],
    lengths: [16],
    code: {
      name: CVC,
      size: 3
    }
  };

  Drupal.commerceMangopay.cardTypes[AMERICAN_EXPRESS] = {
    niceType: 'American Express',
    type: AMERICAN_EXPRESS,
    prefixPattern: /^(3|34|37)$/,
    exactPattern: /^3[47]\d*$/,
    isAmex: true,
    gaps: [4, 10],
    lengths: [15],
    code: {
      name: CID,
      size: 4
    }
  };

  Drupal.commerceMangopay.cardTypes[DINERS_CLUB] = {
    niceType: 'Diners Club',
    type: DINERS_CLUB,
    prefixPattern: /^(3|3[0689]|30[0-5])$/,
    exactPattern: /^3(0[0-5]|[689])\d*$/,
    gaps: [4, 10],
    lengths: [14, 16, 19],
    code: {
      name: CVV,
      size: 3
    }
  };

  Drupal.commerceMangopay.cardTypes[DISCOVER] = {
    niceType: 'Discover',
    type: DISCOVER,
    prefixPattern: /^(6|60|601|6011|65|64|64[4-9])$/,
    exactPattern: /^(6011|65|64[4-9])\d*$/,
    gaps: [4, 8, 12],
    lengths: [16, 19],
    code: {
      name: CID,
      size: 3
    }
  };

  Drupal.commerceMangopay.cardTypes[JCB] = {
    niceType: 'JCB',
    type: JCB,
    prefixPattern: /^(2|21|213|2131|1|18|180|1800|3|35)$/,
    exactPattern: /^(2131|1800|35)\d*$/,
    gaps: [4, 8, 12],
    lengths: [16],
    code: {
      name: CVV,
      size: 3
    }
  };

  Drupal.commerceMangopay.cardTypes[UNIONPAY] = {
    niceType: 'UnionPay',
    type: UNIONPAY,
    prefixPattern: /^((6|62|62\d|(621(?!83|88|98|99))|622(?!06)|627[02,06,07]|628(?!0|1)|629[1,2])|622018)$/,
    exactPattern: /^(((620|(621(?!83|88|98|99))|622(?!06|018)|62[3-6]|627[02,06,07]|628(?!0|1)|629[1,2]))\d*|622018\d{12})$/,
    gaps: [4, 8, 12],
    lengths: [16, 17, 18, 19],
    code: {
      name: CVN,
      size: 3
    }
  };

  Drupal.commerceMangopay.cardTypes[MAESTRO] = {
    niceType: 'Maestro',
    type: MAESTRO,
    prefixPattern: /^(5|5[06-9]|6\d*)$/,
    exactPattern: /^(5[06-9]|6[37])\d*$/,
    gaps: [4, 8, 12],
    lengths: [12, 13, 14, 15, 16, 17, 18, 19],
    code: {
      name: CVC,
      size: 3
    }
  };

  /**
   * Based on https://github.com/braintree/credit-card-type
   *
   * @param cardNumber
   * @returns String
   */
  Drupal.commerceMangopay.getCardType = function(cardNumber) {
    if (!(typeof cardNumber === 'string' || cardNumber instanceof String)) {
      return null;
    }

    if (cardNumber.length === 0) {
      return null;
    }

    var testOrder = [
      VISA,
      MASTERCARD,
      AMERICAN_EXPRESS,
      DINERS_CLUB,
      DISCOVER,
      JCB,
      UNIONPAY,
      MAESTRO
    ];

    var type, typeObject, i;
    var prefixResults = [];
    var exactResults = [];

    for (i = 0; i < testOrder.length; i++) {
      type = testOrder[i];
      typeObject = Drupal.commerceMangopay.cardTypes[type];

      if (typeObject.exactPattern.test(cardNumber)) {
        exactResults.push(typeObject);
      } else if (typeObject.prefixPattern.test(cardNumber)) {
        prefixResults.push(typeObject);
      }
    }

    return exactResults.length ? exactResults[0].type : prefixResults[0].type;
  };

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
        var submitButton = $(this);

        // Prevent multiple clicks
        submitButton.attr("disabled", true);

        // TODO: Validate if we have card data present and if it's the right format.
        var billingInformationInput = Drupal.commerceMangopay.getBillingInformationInput();
        var cardInput = Drupal.commerceMangopay.getCardInput();
        var cardType = Drupal.commerceMangopay.getCardType(cardInput.cardNumber);
        if (!cardType) {
          return;
        }

        // First, get user data from MANGOPAY API.
        var currencyCode = "EUR"; // TODO: Fetch it from the cart?
        $.ajax({
            method: "POST",
            url: "/commerce-mangopay/preregister-card/" + drupalSettings.commerceMangopay.paymentGatewayId,
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
          .done(function(preregisterResponse) {
            // Secondly, initialize card registration object with get-user provided data
            mangoPay.cardRegistration.init({
              cardRegistrationURL : preregisterResponse.cardRegistrationURL,
              preregistrationData : preregisterResponse.preregistrationData,
              accessKey : preregisterResponse.accessKey,
              Id : preregisterResponse.cardRegistrationId
            });

            // Register card with MANGOPAY
            mangoPay.cardRegistration.registerCard(cardInput,
              function(registerResponse) {

                // According to MANGOPAY docs, we have to update card register here:
                // https://docs.mangopay.com/endpoints/v2.01/cards#e178_create-a-card-registration
                // But, it appears javascript MANGOPAY toolkit does it automatically for us. NICE!

                // Save relevant data in the hidden fields on the form and pass the form over to Drupal for processing.
                $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-card-type"]').val(cardType);
                // WARNING: We can transfer and store ONLY last 4 digits of the card in Drupal.
                // Full card information is passed over to MANGOPAY.
                $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-card-alias"]').val(cardInput.cardNumber.substr(cardInput.cardNumber.length - 4));
                $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-card-id"]').val(registerResponse.CardId);
                $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-user-id"]').val(preregisterResponse.userId);
                $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-wallet-id"]').val(preregisterResponse.walletId);

                // Submit the whole and pass on control to actual Commerce checkout routines.
                submitButton.parents('form').first().submit();
              },
              function(errorResponse){
                // TODO: Handle error - https://docs.mangopay.com/guide/errors
                console.log(errorResponse);
                submitButton.removeAttr("disabled");
              }
            );
          })
          .fail(function() {
            // TODO: Handle error - https://docs.mangopay.com/guide/errors
            console.log("Error");
            submitButton.removeAttr("disabled");
          });

        event.preventDefault();
      });

    }
  };

})(jQuery, Drupal, drupalSettings);