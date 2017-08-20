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
   * TODO: Replace the code validating cards with some kind of third party library
   * i.e. https://github.com/braintree/card-validator
   *
   * @param cardNumber
   * @returns String
   */
  Drupal.commerceMangopay.matchCardType = function(number) {
    if (!(typeof number === 'string' || number instanceof String)) {
      return null;
    }

    if (number.length === 0) {
      return null;
    }

    if (!Drupal.commerceMangopay.luhnCheck(number)) {
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

    for (i = 0; i < testOrder.length; i++) {
      type = testOrder[i];
      typeObject = Drupal.commerceMangopay.cardTypes[type];

      // Grab the first matching
      if (typeObject.exactPattern.test(number)) {
        return typeObject;
      }
    }

    return null;
  };

  /**
   * Return card type if the card is valid.
   *
   * @param number
   * @returns {*}
   */
  Drupal.commerceMangopay.cardType = function(number) {
    var cardTypeObject = Drupal.commerceMangopay.matchCardType(number);

    if (cardTypeObject) {
      return cardTypeObject.type;
    }

    return null;
  };

  /**
   * Luhn algorithm for card validity
   * From https://gist.github.com/ShirtlessKirk/2134376
   */
  Drupal.commerceMangopay.luhnCheck = function (arr) {
    return function (ccNum) {
      var
        len = ccNum.length,
        bit = 1,
        sum = 0,
        val;

      while (len) {
        val = parseInt(ccNum.charAt(--len), 10);
        sum += (bit ^= 1) ? arr[val] : val;
      }

      return sum && sum % 10 === 0;
    };
  }([0, 2, 4, 6, 8, 1, 3, 5, 7, 9]);

  /**
   * Sets error on the form.
   *
   * @param form
   * @param formField
   * @param error
   */
  Drupal.commerceMangopay.setError = function (form, formField, error) {
    if (formField) {
      var parentElement = formField.parents('.js-form-item').first();
      parentElement.addClass('error').addClass('has-error');
    }

    if (error) {
      $('.js-messages', form).show();
      $('.js-messages ul', form).append($('<li/>', {text: error}));
    }
  };

  /**
   * Clears all errors on the form.
   *
   * @param form
   */
  Drupal.commerceMangopay.clearErrors = function (form) {
    $('.js-form-item.has-error', form).each(function() {
      $(this).removeClass('has-error').removeClass('error');
    });

    $('.js-messages', form).hide();
    $('.js-messages ul', form).empty();
  };

  /**
   * Validates required fields on the form.
   *
   * @param form
   * @returns {boolean}
   */
  Drupal.commerceMangopay.validateRequired = function(form) {
    var hasErrors = false;

    // Loop through all required fields and mark them as errorneous if no input provided.
    $('input[required], select[required]', form).each(function () {
      if (!$(this).val()) {
        Drupal.commerceMangopay.setError(form, $(this));
        hasErrors = true;
      }
    });

    if (hasErrors) {
      Drupal.commerceMangopay.setError(form, null, Drupal.t('Please fill required fields'));
    }

    return !hasErrors;
  };

  /**
   * Validates card number.
   *
   * @param form
   * @returns {boolean}
   */
  Drupal.commerceMangopay.validateCard = function(form) {
    var cardField = $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-number"]', form);

    // Is it not empty?
    if (!cardField.val()) {
      return false;
    }

    // Is it all numeric?
    if (!/^\d+$/.test(cardField.val())) {
      Drupal.commerceMangopay.setError(form, cardField, Drupal.t('Card number must contain only digits'));
      return false;
    }

    // Can card type be determined?
    if (!Drupal.commerceMangopay.matchCardType(cardField.val())) {
      Drupal.commerceMangopay.setError(form, cardField, Drupal.t('Card number is invalid'));
      return false;
    }

    return true
  };

  /**
   * Validates card expiry date.
   *
   * @param form
   * @returns {boolean}
   */
  Drupal.commerceMangopay.validateExpiry = function(form) {
    var monthField = $('select[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-expiration-month"]', form);
    var yearField = $('select[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-expiration-year"]', form);

    var year = '20' + yearField.val();
    var month = monthField.val();
    var expiryDate = new Date(year, month, 0, 0, 0, 0, 0); // Last day of the month
    var now = new Date();

    // Is the expiry date in the past?
    if (expiryDate < now) {
      Drupal.commerceMangopay.setError(form, monthField);
      Drupal.commerceMangopay.setError(form, yearField, Drupal.t('Expiry date must be in the future'));
      return false;
    }

    return true;
  };

  /**
   * Validates CVV number.
   *
   * @param form
   * @returns {boolean}
   */
  Drupal.commerceMangopay.validateCvx = function(form) {
    var cvxField = $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-security-code"]', form);
    var cardField = $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-number"]', form);

    // Is card number provided?
    // If not, we won't be able to validate the CVV length so we assume the validation is failed.
    if (!cardField.val()) {
      Drupal.commerceMangopay.setError(form, cvxField);
      return false;
    }

    // Is it not empty?
    if (!cvxField.val()) {
      Drupal.commerceMangopay.setError(form, cvxField);
      return false;
    }

    // Is it all numeric?
    if (!/^\d+$/.test(cvxField.val())) {
      Drupal.commerceMangopay.setError(form, cvxField, Drupal.t('CVV code is invalid'));
      return false;
    }

    // Do we have valid card with correct card type matched?
    // If not, we won't be able to validate the CVV length so we assume the validation is failed.
    var cardTypeObject = Drupal.commerceMangopay.matchCardType(cardField.val());
    if (!cardTypeObject) {
      Drupal.commerceMangopay.setError(form, cvxField, Drupal.t('CVV code is invalid'));
      return false;
    }

    // Is the CVV correct length?
    if (cvxField.val().length != cardTypeObject.code.size) {
      Drupal.commerceMangopay.setError(form, cvxField, Drupal.t('CVV code is invalid'));
      return false;
    }

    return true;
  };

  /**
   * Helper function. Returns card input object for use in mangoPay.cardRegistration.registerCard
   *
   * @param form
   * @returns {{cardNumber: *, cardExpirationDate: *, cardCvx: *, cardType: (Drupal.commerceMangopay.cardType|*|string|string)}}
   */
  Drupal.commerceMangopay.getCardInput = function(form) {
    return {
      cardNumber: $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-number"]', form).val(),
      cardExpirationDate: $('select[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-expiration-month"]', form).val()
      + $('select[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-expiration-year"]', form).val(),
      cardCvx: $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-security-code"]', form).val(),
      cardType: drupalSettings.commerceMangopay.cardType  // This is MANGOPAY's card type as configured. Not actual type of the card entered.
    };
  };

  /**
   *
   * Helper function. Returns Billing information object for use in various ajax calls.
   *
   * @param form
   * @returns {{firstName: *, lastName: *, addressLine1: *, addressLine2: *, postalCode: *, city: *, country: *, email: string}}
   */
  Drupal.commerceMangopay.getBillingInformationInput = function(form) {
    return {
      firstName: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-given-name"]', form).val(),
      lastName: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-family-name"]', form).val(),
      addressLine1: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-address-line1"]', form).val(),
      addressLine2: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-address-line2"]', form).val(),
      postalCode: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-postal-code"]', form).val(),
      city: $('input[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-locality"]', form).val(),
      country: $('select[data-drupal-selector="edit-payment-information-add-payment-method-billing-information-address-0-address-country-code"]', form).val(),
      email: 'jan@example.com' // TODO: Fetch this form the user object.
    };
  };

  /**
   * Registers card with MANGOPAY
   * Follows the process documented here:
   * https://docs.mangopay.com/endpoints/v2.01/cards#e178_create-a-card-registration
   *
   * @param form
   */
  Drupal.commerceMangopay.registerCard = function(form,completed) {
    var hasErrors = false;

    // Validate the form and inputs
    Drupal.commerceMangopay.clearErrors();
    if (!Drupal.commerceMangopay.validateRequired(form)) {
      hasErrors = true;
    }
    if (!Drupal.commerceMangopay.validateCard(form)) {
      hasErrors = true;
    }
    if (!Drupal.commerceMangopay.validateCvx(form)) {
      hasErrors = true;
    }
    if (!Drupal.commerceMangopay.validateExpiry(form)) {
      hasErrors = true;
    }

    // If there are any validation errors, scroll to the top and do not continue.
    if (hasErrors) {
      $('html, body').animate({scrollTop: 0}, 200);
      completed(false);
      return;
    }

    // Get user, wallet and card preregistration data.
    var billingInformationInput = Drupal.commerceMangopay.getBillingInformationInput(form);
    $.ajax({
        method: "POST",
        url: "/commerce-mangopay/preregister-card/" + drupalSettings.commerceMangopay.paymentGatewayId,
        data: {
          card_type: drupalSettings.commerceMangopay.cardType, // This is MANGOPAY's card type as configured. Not actual type of the card entered.
          currency_code: drupalSettings.commerceMangopay.currencyCode,
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
        // Register baseURL and client ID with MANGOPAY object.
        mangoPay.cardRegistration.baseURL = drupalSettings.commerceMangopay.baseUrl;
        mangoPay.cardRegistration.clientId = drupalSettings.commerceMangopay.clientId;

        // Initialize card registration object with preregister-card provided data
        mangoPay.cardRegistration.init({
          cardRegistrationURL : preregisterResponse.cardRegistrationURL,
          preregistrationData : preregisterResponse.preregistrationData,
          accessKey : preregisterResponse.accessKey,
          Id : preregisterResponse.cardRegistrationId
        });

        // Finally, register the card with MANGOPAY
        var cardInput = Drupal.commerceMangopay.getCardInput(form);
        mangoPay.cardRegistration.registerCard(cardInput,
          function(registerResponse) {

            // According to MANGOPAY docs, we have to update card register here:
            // https://docs.mangopay.com/endpoints/v2.01/cards#e178_create-a-card-registration
            // But, it appears javascript MANGOPAY toolkit does it automatically for us. NICE!

            // Save relevant data in the hidden fields on the form and pass the form over to Drupal for processing.
            $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-card-type"]', form).val(Drupal.commerceMangopay.cardType(cardInput.cardNumber));
            // WARNING: We can transfer and store ONLY last 4 digits of the card in Drupal.
            // Full card information is passed over to MANGOPAY.
            $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-card-alias"]', form).val(cardInput.cardNumber.substr(cardInput.cardNumber.length - 4));
            $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-card-id"]', form).val(registerResponse.CardId);
            $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-user-id"]', form).val(preregisterResponse.userId);
            $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-wallet-id"]', form).val(preregisterResponse.walletId);

            completed(true);

            // Submit the whole and pass on control to actual Commerce checkout routines.
            form.submit();
          },
          function(errorResponse){
            // TODO: Introduce more descriptive user messages for some more common errors: https://docs.mangopay.com/guide/errors
            Drupal.commerceMangopay.setError(form, null, Drupal.t('We have encountered problems while processing your card. Please confirm the details you entered are correct or try a different card.'));
            completed(false);
          }
        );
      })
      .fail(function() {
        Drupal.commerceMangopay.setError(form, null, Drupal.t('Unexpected error occurred while processing your card. Please confirm the details you entered are correct or try a different card. If the problem persists, please contact us.'));
        completed(false);
      });
  };

  /**
   * 
   * @type {{attach: Drupal.behaviors.commerceMangopayRegisterCard.attach}}
   */
  Drupal.behaviors.commerceMangopayRegisterCard = {
    attach: function (context, settings) {
      var submitButton = $('button[data-drupal-selector="edit-actions-next"]');
      var form = submitButton.parents('form').first();

      // Check if we actually have add payment method form opened (by looking for a specific field).
      // If not, make sure we've got our click event removed from the next button.
      // We have to do this in case this script is attached by the add new payment method
      // form, but then the form is closed when user selects existing payment method.
      if (!$('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-number"]', form).length) {
        submitButton.off('click');
        submitButton.removeClass('js-processed');
      }

      // Otherwise capture submit button. We want to do things with MANGOPAY card registration js kit before submitting to Drupal.
      else {
        // Initially hide messages. Show only when there are js errors.
        Drupal.commerceMangopay.clearErrors(form);

        // Card number field must not contain spaces
        $('input[data-drupal-selector="edit-payment-information-add-payment-method-payment-details-number"]:not(".js-processed")', form).addClass('js-processed').change(function(event) {
          $(this).val($(this).val().replace(/\s/g, ""));
        });

        // Attach event to the button.
        $('button[data-drupal-selector="edit-actions-next"]:not(".js-processed")', form).addClass('js-processed').click(function(event) {
          // Always prevent default on click.
          event.preventDefault();

          // Prevent multiple clicks
          submitButton.attr("disabled", true);

          // Call register card
          Drupal.commerceMangopay.registerCard(form, function(success) {
            if (!success) { submitButton.removeAttr("disabled"); }
          });
        });
      }

    }
  };

})(jQuery, Drupal, drupalSettings);