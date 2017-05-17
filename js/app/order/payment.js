var Card = require("card");

var card = new Card({
    form: 'form',
    container: '.card',
    formSelectors: {
      numberInput: 'input#number',
      expiryInput: 'input#exp_month, input#exp_year',
      cvcInput: 'input#cvc',
    },
    placeholders: {
      number: '•••• •••• •••• ••••',
      name: 'Full Name',
      expiry: '••/••',
      cvc: '•••'
    },
});

function stripeResponseHandler(status, response) {
  // Grab the form:
  var $form = $('#payment-form');

  if (response.error) { // Problem!

    // Show the errors on the form:
    $form.find('.alert')
      .text(response.error.message)
      .removeClass('hidden');

    $form.find('[type="submit"]')
      .removeClass('disabled')
      .prop('disabled', false); // Re-enable submission

  } else { // Token was created!

    // Get the token ID:
    var token = response.id;

    // Insert the token ID into the form so it gets submitted to the server:
    $form.append($('<input type="hidden" name="stripeToken">').val(token));

    // Submit the form:
    $form.get(0).submit();
  }
}

$(function() {
  var $form = $('#payment-form');
  $form.submit(function(event) {
    $form.find('.alert')
      .text('')
      .addClass('hidden');

    // Disable the submit button to prevent repeated clicks:
    $form.find('[type="submit"]')
      .addClass('disabled')
      .prop('disabled', true);

    // Request a token from Stripe:
    Stripe.card.createToken($form, stripeResponseHandler);

    // Prevent the form from being submitted:
    return false;
  });
});
