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

    this.createPaymentFlowOnChangeListener = (resolve) => {
      return (event) => {

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
    }

    // Placeholder function, will be overriden when createToken() is called
    this.submitPaymentListener = (event) => console.log(event)

    this.createSubmitPaymentListener = (resolve, reject) => {

      return (event) => {

        console.log('submitPaymentListener', event)

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
              // FIXME
              console.log(event.type, event.detail)
            }
          );

          /*
          window.paygreenjs.attachEventListener(
            paygreenjs.Events.PAYMENT_FLOW_ONCHANGE,
            (event) => {

              console.log(event)
              console.log(window.paygreenjs.status())

              if (!event?.detail?.method && paygreenjs.status().flows[0].status === 'success') {
                this.listeners.forEach(cb => {
                  if (typeof cb === 'function') {
                    cb(event, null, true);
                  }
                });
                this.listeners = [];
                window.paygreenjs.setPaymentMethod('bank_card');
              }
            }
          );
          */

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
            (event) => {
              this.submitPaymentListener(event)
              /*
              console.log(event.type, event.detail)
              this.listeners.forEach(cb => {
                if (typeof cb === 'function') {
                  cb(event, response.data.id, true);
                }
              });
              this.listeners = [];
              */
            }
          );

          window.paygreenjs.attachEventListener(
            paygreenjs.Events.PAYMENT_FAIL,
            (event) => {
              this.submitPaymentListener(event)
              /*
              console.log(event.type, event.detail)
              this.listeners.forEach(cb => {
                if (typeof cb === 'function') {
                  cb(event, response.data.id, false);
                }
              });
              this.listeners = [];
              */
            }
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
    console.log('createToken')
    return new Promise((resolve, reject) => {

      console.log(window.paygreenjs.status())


      /*
      this.listeners.push((event, paymentOrderID, success) => {
        if (success) {
          resolve(paymentOrderID);
        } else {
          reject(new Error('Try again later'));
        }
      })
      this.
      */

      this.submitPaymentListener = this.createSubmitPaymentListener(resolve, reject);
      window.paygreenjs.submitPayment();
    });
  }
}
