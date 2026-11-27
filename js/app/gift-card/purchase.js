/**
 * The gift card purchase form.
 *
 * Deliberately standalone rather than reusing the checkout's PaymentForm:
 * that component is built around an order -- it talks to the payment-method
 * endpoint, juggles split payments and saved cards, and expects a cart to
 * exist. Buying a gift card is one amount, one card, no order, so wiring it
 * through all of that would mean teaching PaymentForm about a case that
 * isn't a checkout.
 */

/* global Stripe */

const form = document.querySelector('#gift-card-form')

if (form) {
  const errorEl = document.querySelector('#gift-card-error')
  const submitEl = document.querySelector('#gift-card-submit')

  const stripe = Stripe(form.dataset.stripeKey)
  const card = stripe.elements().create('card', { hidePostalCode: true })
  card.mount('#gift-card-stripe-element')

  const showError = message => {
    errorEl.textContent = message
    errorEl.style.display = 'block'
    submitEl.disabled = false
  }

  const clearError = () => {
    errorEl.textContent = ''
    errorEl.style.display = 'none'
  }

  card.on('change', event => {
    if (event.error) {
      showError(event.error.message)
    } else {
      clearError()
    }
  })

  // The preset amount buttons just fill the amount field, so a customer who
  // wants a figure that isn't offered can still type one.
  const amountField = form.querySelector('[id$="_amount"]')
  document.querySelectorAll('#gift-card-denominations button').forEach(button => {
    button.addEventListener('click', () => {
      if (amountField) {
        amountField.value = button.dataset.amount
      }
      document
        .querySelectorAll('#gift-card-denominations button')
        .forEach(other => other.classList.toggle('btn-primary', other === button))
    })
  })

  const post = (url, body) =>
    fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body,
    }).then(response => response.json().then(data => ({ ok: response.ok, data })))

  form.addEventListener('submit', event => {
    event.preventDefault()
    clearError()
    submitEl.disabled = true

    stripe
      .createPaymentMethod({ type: 'card', card })
      .then(({ error, paymentMethod }) => {
        if (error) {
          showError(error.message)
          return
        }

        const body = new FormData(form)
        body.append('paymentMethodId', paymentMethod.id)

        return post(form.dataset.purchaseUrl, body).then(({ ok, data }) => {
          if (!ok) {
            showError(data.error)
            return
          }

          if (data.redirectUrl) {
            window.location.href = data.redirectUrl
            return
          }

          // The bank wants the customer to confirm. Once they have, the
          // server finishes the job -- the browser never decides on its own
          // that a payment succeeded.
          if (data.requiresAction) {
            return stripe.handleCardAction(data.clientSecret).then(result => {
              if (result.error) {
                showError(result.error.message)
                return
              }

              const confirmBody = new FormData()
              confirmBody.append('giftCardId', data.giftCardId)

              return post(form.dataset.confirmUrl, confirmBody).then(({ ok, data }) => {
                if (!ok) {
                  showError(data.error)
                  return
                }
                window.location.href = data.redirectUrl
              })
            })
          }
        })
      })
      .catch(() => showError('An unexpected error occurred.'))
  })
}
