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

function onClickBack(e) {
  e.preventDefault()
  window.paygreenjs.setPaymentMethod(null)
}

export default {
  async init() {
    this.listeners = []

    this.toggleChangePaymentPlatform = (event) => {
      if (event?.detail?.method) {
        document.querySelector('#paygreen-back').classList.remove('d-none')
      } else {
        document.querySelector('#paygreen-back').classList.add('d-none')
      }
    }

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
        case paygreenjs.Events.FULL_PAYMENT_DONE:
          const { paymentOrder } = window.paygreenjs.status()
          resolve(paymentOrder.id)
          break;
        case paygreenjs.Events.PAYMENT_FAIL:
          // TODO Use the actual error message
          reject(new Error('Try again later'));
          break;
      }
    }

  },
  async mount() {
    return new Promise((resolve, reject) => {
      axios.post(this.config.gatewayConfig.createPaymentOrderURL)
        .then(response => {

          // This will show/hide the button to go back to platform selection
          window.paygreenjs.attachEventListener(
            paygreenjs.Events.PAYMENT_FLOW_ONCHANGE,
            this.toggleChangePaymentPlatform
          );

          window.paygreenjs.attachEventListener(
            paygreenjs.Events.ERROR,
            (event) => {
              // FIXME Grab the actuall error message
              console.log(event.type, event.detail)
            }
          );

          // This will enable the submit button once the card is 100% valid
          window.paygreenjs.attachEventListener(
            paygreenjs.Events.CARD_ONCHANGE,
            (event) => {
              this.config.onChange({ complete: event.detail.valid })
            }
          );

          window.paygreenjs.attachEventListener(
            paygreenjs.Events.PAYMENT_FLOW_ONCHANGE,
            this.createPaymentFlowOnChangeListener(resolve)
          );

          window.paygreenjs.attachEventListener(
            paygreenjs.Events.FULL_PAYMENT_DONE,
            this.submitPaymentListener
          );

          window.paygreenjs.attachEventListener(
            paygreenjs.Events.PAYMENT_FAIL,
            this.submitPaymentListener
          );

          window.paygreenjs.init({
            paymentOrderID: response.data.id,
            objectSecret: response.data.object_secret,
            publicKey: this.config.gatewayConfig.publicKey,
            mode: 'payment',
            displayAuthentication: 'modal',
            style,
          });

          document.querySelector('#paygreen-back > a').addEventListener('click', onClickBack, false)

          // We do not resolve the promise here
          // It will be resolved in the PAYMENT_FLOW_ONCHANGE event listener

        })
        .catch(e => {
          reject(new Error(e.response.data.error.message))
        })
    })
  },
  unmount() {
    const status = window.paygreenjs.status()
    if (status.paymentOrder) {
      window.paygreenjs.unmount(true)
      document.querySelector('#paygreen-back').classList.add('d-none')
      document.querySelector('#paygreen-back > a').removeEventListener('click', onClickBack, false)
    }
  },
  async createToken() {
    return new Promise((resolve, reject) => {
      this.submitPaymentListener = this.createSubmitPaymentListener(resolve, reject);
      window.paygreenjs.submitPayment();
    });
  }
}
