/* eslint-disable */

import axios from 'axios'

const style = {
  input: {
    base: {
      color: 'black',
      fontSize: '18px',
      padding: '4px',
    },
    hover: {
      color: 'grey',
    },
    focus: {
      color: 'grey',
    },
    invalid: {
      color: 'red',
    },
    placeholder: {
      base: {
        color: 'grey',
      },
    },
  },
  checkbox: {
    label: {
      base: {
        color: 'black',
      },
      unchecked: {
        color: 'black',
      },
    },
    box: {
      base: {
        color: '#0d6efd',
        hover: {
          color: '#424242',
        },
      },
      unchecked: {
        color: '#0d6efd',
      },
    },
  },
};

export default {
  async init() {
    this.listeners = []

    this.createPaymentFlowOnChangeListener = (resolve) => (event) => {

      console.log(event.type, event.detail)

      if (!event?.detail?.method && event?.detail?.status === 'pending') {
        // We do not enable the submit button for now
        resolve(false)
      }

      // https://developers.paygreen.fr/recipes/pgjs-conecs-bank-card-payment
      // If the first flow was successful, we ask to pay the rest by credit card
      if (!event?.detail?.method && paygreenjs.status().flows[0].status === 'success') {
        window.paygreenjs.setPaymentMethod('bank_card');
      }
    }

    // Placeholder function, will be overriden when createToken() is called
    this.submitPaymentListener = (event) => console.log(event)

    this.createSubmitPaymentListener = (resolve, reject) => (event) => {
      switch (event.type) {
        case window.paygreenjs.Events.FULL_PAYMENT_DONE:
          const { paymentOrder } = window.paygreenjs.status()
          resolve(paymentOrder.id)
          break;
        case window.paygreenjs.Events.PAYMENT_FAIL:
          reject(event.detail?.error || new Error('An error occurred'));
          break;
        case window.paygreenjs.Events.ERROR:
          reject(event.detail);
          break;
      }
    }

  },
  async mount(el, method, options, formOptions) {

    this.config.gatewayConfig = {
      ...this.config.gatewayConfig,
      ...options.paygreen
    }

    return new Promise((resolve, reject) => {

      axios.post(this.config.gatewayConfig.createPaymentOrderURL)
        .then(response => {

          window.paygreenjs.attachEventListener(
            window.paygreenjs.Events.ERROR,
            (event) => this.submitPaymentListener(event)
          );

          // This will enable the submit button once the card is 100% valid
          window.paygreenjs.attachEventListener(
            window.paygreenjs.Events.CARD_ONCHANGE,
            (event) => {
              this.config.onChange({ complete: event.detail.valid })
            }
          );

          window.paygreenjs.attachEventListener(
            window.paygreenjs.Events.PAYMENT_FLOW_ONCHANGE,
            this.createPaymentFlowOnChangeListener(resolve)
          );

          window.paygreenjs.attachEventListener(
            window.paygreenjs.Events.FULL_PAYMENT_DONE,
            (event) => this.submitPaymentListener(event)
          );

          window.paygreenjs.attachEventListener(
            window.paygreenjs.Events.PAYMENT_FAIL,
            (event) => this.submitPaymentListener(event)
          );

          // Move to next field automatically
          // https://developers.paygreen.fr/docs/paygreenjs-customization#change-focus
          window.paygreenjs.attachEventListener(window.paygreenjs.Events.PAN_FIELD_FULFILLED, () => {
            paygreenjs.focus('exp');
          });
          window.paygreenjs.attachEventListener(window.paygreenjs.Events.EXP_FIELD_FULFILLED, () => {
            paygreenjs.focus('cvv');
          });

          let paygreenOptions = {
            paymentOrderID: response.data.id,
            objectSecret: response.data.object_secret,
            publicKey: this.config.gatewayConfig.publicKey,
            mode: 'payment',
            displayAuthentication: 'modal',
            style,
            paymentMethod: method === 'card' ? 'bank_card' : method,
          }

          const status = window.paygreenjs.status();
          const isPaygreenInitialized = status?.paymentOrder ?? false;

          if (!isPaygreenInitialized) {
            window.paygreenjs.init(paygreenOptions);
          } else {
            window.paygreenjs.setPaymentMethod(method === 'card' ? 'bank_card' : method);
          }

          // We do not resolve the promise here
          // It will be resolved in the PAYMENT_FLOW_ONCHANGE event listener

        })
        .catch(e => {
          console.log(e)
          reject(new Error(e.response.data.error.message))
        })
    })
  },
  unmount() {
    const status = window.paygreenjs.status()
    if (status?.paymentOrder) {
      window.paygreenjs.unmount(true)
    }
  },
  async createToken() {
    return new Promise((resolve, reject) => {
      this.submitPaymentListener = this.createSubmitPaymentListener(resolve, reject);
      window.paygreenjs.submitPayment();
    });
  }
}
