import React, { useState, useEffect } from 'react'
import axios from 'axios'
import { render, unmountComponentAtNode } from 'react-dom'
import { Elements, CardElement, ElementsConsumer } from '@stripe/react-stripe-js'
import { useTranslation } from 'react-i18next'
import _ from 'lodash'

import i18n, { getCountry } from '../../i18n'
import SavedCreditCard from './SavedCreditCard'
import { isGuest } from './utils'

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

function handleSaveOfPaymentMethod(el) {
  return new Promise((resolve) => {
    if (el.saveCard && el.config.gatewayConfig.account) {
      // if user chose to save the payment method and we are in a connected account

      /**
       * From Stripe support: You'll need to create a new payment method and as you are passing a customer ID,
       * the payment method created by the setupintent will be automatically attached to the customer.
      */
      return el.stripe.createPaymentMethod({
        type: 'card',
        card: el.elements.getElement(CardElement),
        billing_details: {
          name: el.cardholderName,
        }
      }).then((createPaymentMethodResult) => {
        if (createPaymentMethodResult.error) {
          resolve()
        } else {
          axios.post(el.config.gatewayConfig.createSetupIntentOrAttachPMURL, {
            payment_method_to_save: createPaymentMethodResult.paymentMethod.id
          })
          .then(() => resolve())
          .catch(e => {
            // do not interrupt flow if there is an error with this
            if (e.response) {
              console.log(e.response.data.error.message)
            } else {
              console.log('An unexpected error occurred while trying to create a SetupIntent')
            }
            resolve()
          })
        }
      })
    } else {
      resolve()
    }
  })
}

// @see https://stripe.com/docs/payments/accept-a-payment-synchronously

function handleServerResponse(response, config) {

  return new Promise((resolve, reject) => {
    if (response.requires_action) {
      let stripeOptions = {}

      if (config.gatewayConfig.account) {
        stripeOptions = {
          ...stripeOptions,
          stripeAccount: config.gatewayConfig.account,
        }
      }
      const stripe = Stripe(config.gatewayConfig.publishableKey, stripeOptions)

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

const StripeForm = ({ onChange, onCardholderNameChange, options, country, cards, onSaveCreditCardChange, onSavedCreditCardSelected, formOptions }) => {

  const { t } = useTranslation()

  const [addNewCard, setAddNewCard] = useState(false)

  const hasBreakdown = !!options
    && Object.prototype.hasOwnProperty.call(options, 'amount_breakdown')
    && Object.prototype.hasOwnProperty.call(options.amount_breakdown, 'edenred')
    && Object.prototype.hasOwnProperty.call(options.amount_breakdown, 'card')

  const thereAreCardsToShow = cards && cards.length

  const handleCardClicked = (e, c) => {
    if (e.target.checked) {
      onSavedCreditCardSelected(c)
      setAddNewCard(false)
    }
  }

  const toggleCardForm = () => {
    // blank saved payment method selected
    onSavedCreditCardSelected(null)
    const selectedCard = document.querySelector('input[type="radio"][name="credit-cards"]:checked')
    if (selectedCard) {
      selectedCard.checked = false
    }

    setAddNewCard(!addNewCard)
  }

  useEffect(() => {
    if (addNewCard) {
      document.getElementById('card-form').scrollIntoView()
    }
  }, [addNewCard])

  return (
    <React.Fragment>
      {
        thereAreCardsToShow ?
        <div>
          <div className="form-group">
            <label className="control-label">
              {t('PAY_WITH_SAVED_CREDIT_CARD')}
            </label>
            {
              cards.map((c) => {
                return (
                  <div className="d-flex align-items-center mb-2" key={c.id}>
                    <input type="radio" name="credit-cards" id={c.id} className="mr-4" onClick={(e) => handleCardClicked(e, c)} />
                    <SavedCreditCard card={c.card} />
                  </div>
                )
              })
            }
          </div>
          <button type="button" className="btn btn-primary mb-4" onClick={() => toggleCardForm()}>
            {t('ADD_NEW_CREDIT_CARD')}
          </button>
        </div> : null
      }
      {
        (addNewCard || !thereAreCardsToShow || isGuest(formOptions)) ?
        <div id="card-form">
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
          {
            !isGuest(formOptions) ?
            <div className="form-group">
              <div className="checkbox">
                <label>
                  <input type="checkbox" onChange={(e) => onSaveCreditCardChange(e.target.checked)}/>
                    {t('SAVE_CREDIT_CARD')}
                </label>
              </div>
            </div> : null
          }
        </div> : null
      }
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
  async mount(el, method, options, formOptions) {

    this.cardholderName = ''
    this.el = el
    this.saveCard = false
    this.savedPaymentMethod = null

    if (method === 'giropay') {

      return new Promise((resolve) => {

        render(
          <GiropayForm
            onCardholderNameChange={ cardholderName => {
              this.cardholderName = cardholderName
            }} />, el, resolve)
      })
    }

    let resultCards = []
    if (!isGuest(formOptions)) { // avoid this API call if the customer is a guest
      resultCards = await axios.get(this.config.gatewayConfig.customerPaymentMethodsURL)
    }

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
                cards={ resultCards && resultCards.data && resultCards.data.cards ? resultCards.data.cards.data : [] }
                onSaveCreditCardChange={ saveCard => {
                  this.saveCard = saveCard
                }}
                onSavedCreditCardSelected={ (card) => this.config.onSavedCreditCardSelected(card) }
                formOptions={formOptions}
              />
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
  createToken(savedPaymentMethodId = null) {
    return new Promise((resolve, reject) => {

      return this.getPaymentMethod(savedPaymentMethodId)
        .then((paymentMethodId) => {
          if (this.config.gatewayConfig.account) {
            // for connected account we have to clone the platform payment method
            return axios.post(this.config.gatewayConfig.clonePaymentMethodToConnectedAccountURL, {
              payment_method_id: paymentMethodId,
            }).then((res) => {
              return [paymentMethodId, res.data.cloned_payment_method.id]
            })
          } else {
            // use the platform payment method
            return [paymentMethodId]
          }
        })
        .then(([platformAccountPaymentMethodId, clonedPaymentMethodId]) => {
          axios.post(this.config.gatewayConfig.createPaymentIntentURL, {
            payment_method_id: clonedPaymentMethodId || platformAccountPaymentMethodId,
            save_payment_method: this.saveCard,
          }).then((response) => {
            if (response.data.error) {
              reject(new Error(response.data.error.message))
            } else {
              handleServerResponse(response.data, this.config)
                .then((paymentIntentId) => {
                  handleSaveOfPaymentMethod(this)
                    .then(() => resolve(paymentIntentId))
                })
                .catch(e => reject(new Error(e.error.message)))
            }
          }).catch(e => {
            // https://github.com/axios/axios#handling-errors
            if (e.response) {
              reject(new Error(e.response.data.error.message))
            } else {
              reject(new Error('An unexpected error occurred, please try again later'))
            }
          })
        })
        .catch(e => {
          if (e.message) {
            reject(new Error(e.message))
          } else {
            reject(new Error('An unexpected error occurred, please try again later'))
          }
        })
    })
  },
  getPaymentMethod(savedPaymentMethodId = null) {
    return new Promise((resolve, reject) => {
      if (savedPaymentMethodId) {
        resolve(savedPaymentMethodId)
        return
      }

      if (!savedPaymentMethodId && !this.elements.getElement(CardElement)) {
        reject(new Error(i18n.t('ADD_OR_SELECT_A_CARD_TO_PAY')))
        return
      }

      return this.stripe.createPaymentMethod({
        type: 'card',
        card: this.elements.getElement(CardElement),
        billing_details: {
          name: this.cardholderName,
        }
      }).then((createPaymentMethodResult) => {
        if (createPaymentMethodResult.error) {
          reject(new Error(createPaymentMethodResult.error.message))
        } else {
          resolve(createPaymentMethodResult.paymentMethod.id)
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
