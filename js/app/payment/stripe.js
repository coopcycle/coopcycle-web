import React, { useState, useEffect } from 'react'
import axios from 'axios'
import { render, unmountComponentAtNode } from 'react-dom'
import { Elements, CardElement, ElementsConsumer } from '@stripe/react-stripe-js'
import { useTranslation } from 'react-i18next'
import _ from 'lodash'

import { getCountry } from '../i18n'

const style = {
  base: {
    color: '#32325d',
    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
    fontSmoothing: 'antialiased',
    fontSize: '16px',
    '::placeholder': {
      color: '#aab7c4'
    }
  },
  invalid: {
    color: '#fa755a',
    iconColor: '#fa755a'
  }
}

// @see https://stripe.com/docs/payments/accept-a-payment-synchronously

function handleServerResponse(response, stripe) {

  return new Promise((resolve, reject) => {
    if (response.requires_action) {

      // Use Stripe.js to handle required card action
      stripe.handleCardAction(
        response.payment_intent_client_secret
      ).then(function(result) {
        if (result.error) {
          reject(result)
        } else {
          resolve(result.paymentIntent.id)
        }
      })

    } else {
      resolve(response.payment_intent)
    }
  })
}

const CardholderNameInput = ({ onChange }) => {

  const { t } = useTranslation()

  const [ cardholderName, setCardholderName ] = useState('')

  useEffect(() => onChange(cardholderName), [ cardholderName ])

  return (
    <React.Fragment>
      <label className="control-label required">
        { t('PAYMENT_FORM_CARDHOLDER_NAME') }
      </label>
      <input type="text"
        required="required"
        className="form-control"
        value={ cardholderName }
        onChange={ e => setCardholderName(e.target.value) } />
    </React.Fragment>
  )
}

const StripeForm = ({ onChange, onCardholderNameChange, options, country, cards, onSaveCreditCardChange }) => {

  const { t } = useTranslation()

  const hasBreakdown = !!options
    && Object.prototype.hasOwnProperty.call(options, 'amount_breakdown')
    && Object.prototype.hasOwnProperty.call(options.amount_breakdown, 'edenred')
    && Object.prototype.hasOwnProperty.call(options.amount_breakdown, 'card')

  return (
    <React.Fragment>
      { cards && cards.length &&
        <div className="form-group">
          {
            cards.map((c) => {
              return (
                <li key={c.id}>
                  <input type="radio" id={c.id} value={c.id} className="mr-2"></input>
                  <label>
                    {c.card.brand.toUpperCase()} | XXXX - XXXX - XXXX - { c.card.last4 } | { c.card.exp_month }/{ c.card.exp_year }
                  </label>
                </li>
              )
            })
          }
        </div>
      }
      <div className="form-group">
        <CardholderNameInput onChange={ onCardholderNameChange } />
      </div>
      <div className="form-group">
        <label className="control-label hidden">
          { t('PAYMENT_FORM_TITLE') }
        </label>
        <CardElement options={{ style, hidePostalCode: true }} onChange={ onChange } />
        { hasBreakdown && (
          <span className="help-block mt-3">
            <i className="fa fa-info-circle mr-2"></i>
            <span>{ t('EDENRED_SPLIT_AMOUNTS', _.mapValues(options.amount_breakdown, value => (value / 100).formatMoney())) }</span>
          </span>
        )}
        { (!hasBreakdown && _.includes(['es', 'fr'], country)) && (
          <span className="help-block mt-3">
            <i className="fa fa-info-circle mr-2"></i>
            <span>{ t('PAYMENT_FORM_NOT_SUPPORTED') }</span>
          </span>
        )}
      </div>
      <div className="form-group">
        <div className="checkbox">
          <label>
            <input type="checkbox" onChange={(e) => onSaveCreditCardChange(e.target.checked)}/>
              {t('SAVE_CREDIT_CARD')}
          </label>
        </div>
      </div>
    </React.Fragment>
  )
}

const GiropayForm = ({ onCardholderNameChange }) => {

  const { t } = useTranslation()

  return (
    <React.Fragment>
      <div className="form-group">
        <CardholderNameInput onChange={ onCardholderNameChange } />
        <div className="text-center mt-3">
          <span className="help-block">{ t('PAYMENT_FORM_REDIRECT_HELP') }</span>
        </div>
      </div>
    </React.Fragment>
  )
}

export default {
  init() {
    this.stripe = Stripe(this.config.gatewayConfig.publishableKey)
  },
  async mount(el, method, options) {

    this.cardholderName = ''
    this.el = el
    this.saveCard = false

    if (method === 'giropay') {

      return new Promise((resolve) => {

        render(
          <GiropayForm
            onCardholderNameChange={ cardholderName => {
              this.cardholderName = cardholderName
            }} />, el, resolve)
      })
    }

    const resultCards = await axios.get(this.config.gatewayConfig.customerPaymentMethodsURL)

    return new Promise((resolve) => {

      render(
        <Elements stripe={ this.stripe }>
          <ElementsConsumer>
          {({ elements }) => {

            // Keep a reference to Stripe elements
            // FIXME There should be a better way
            this.elements = elements

            return (
              <StripeForm
                country={ getCountry() }
                onChange={ this.config.onChange }
                onCardholderNameChange={ cardholderName => {
                  this.cardholderName = cardholderName
                }}
                options={ options }
                cards={ resultCards.data.cards.data }
                onSaveCreditCardChange={ saveCard => {
                  this.saveCard = saveCard
                }
              }/>
            )
          }}
          </ElementsConsumer>
        </Elements>, el, resolve)
    })
  },
  unmount() {
    if (this.el) {
      unmountComponentAtNode(this.el)
      this.el = null
    }
  },
  createToken() {
    return new Promise((resolve, reject) => {

      // Create a SetupIntent which represents your intent to set up a customer’s payment method to pay now or for future payments.
      axios.post(this.config.gatewayConfig.createCustomerSetupIntentURL, {
        save_payment_method: this.saveCard,
      })
        .then((result) => {
          // submit payment method details to Stripe
          this.stripe.confirmCardSetup(
            result.data.setup_intent.client_secret, {
              payment_method: {
                card: this.elements.getElement(CardElement),
                billing_details: {
                  name: this.cardholderName,
                }
              }
            }
          ).then((result) => {
            if (result.error) {
              reject(new Error(result.error.message))
            } else {
                axios.post(this.config.gatewayConfig.shareCardToConnectedAccountURL, {
                  payment_method: result.setupIntent.payment_method,
                })
                  .then((result) => {
                    axios.post(this.config.gatewayConfig.createPaymentIntentURL, {
                      payment_method_id: result.data.shared_card.id
                    })
                      .then((response) => {
                        if (response.data.error) {
                          reject(new Error(response.data.error.message))
                        } else {
                          handleServerResponse(response.data, this.stripe)
                            .then(paymentIntentId => resolve(paymentIntentId))
                            .catch(e => reject(new Error(e.error.message)))
                        }
                      })
                  })
            }
          })
        })
        .catch(e => {
          // https://github.com/axios/axios#handling-errors
          if (e.response) {
            reject(new Error(e.response.data.error.message))
          } else {
            reject(new Error('An unexpected error occurred, please try again later'))
          }
        })
    })
  },
  // @see https://stripe.com/docs/payments/giropay/accept-a-payment#confirm-giropay-payment
  // https://stripe.com/docs/js/payment_intents/confirm_giropay_payment
  confirmGiropayPayment() {

    return new Promise((resolve, reject) => {

      axios.post(this.config.gatewayConfig.createGiropayPaymentIntentURL)
        .then(response => {
          this.stripe.confirmGiropayPayment(
            response.data.payment_intent_client_secret,
            {
              payment_method: {
                billing_details: {
                  name: this.cardholderName
                }
              },
              return_url: response.data.return_url,
            }
          ).then(function(result) {
            if (result.error) {
              reject(new Error(result.error.message))
            } else {
              resolve()
            }
          })
        })
    })
  }
}
