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

        if (!event?.detail?.method && event?.detail?.status === 'pending') {
          // resolve()
        }

        console.log(event.type, event.detail)

        // https://developers.paygreen.fr/recipes/pgjs-conecs-bank-card-payment
        // If the first flow was successful
        if (!event?.detail?.method && paygreenjs.status().flows[0].status === 'success') {

          /*
          this.listeners.forEach(cb => {
            if (typeof cb === 'function') {
              cb(event, null, true);
            }
          });
          this.listeners = [];
          */

          window.paygreenjs.setPaymentMethod('bank_card');
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

          window.paygreenjs.attachEventListener(
            paygreenjs.Events.CARD_ONCHANGE,
            (event) => {
              console.log(event.type, event.detail)
              this.config.onChange({ complete: event.detail.valid })
            }
          );

          /*
          window.paygreenjs.attachEventListener(
            paygreenjs.Events.PAN_FIELD_ONCHANGE,
            (event) => {
              this.config.onChange({
                error: new Error(event.detail.error)
              })
            }
          );
          */

          window.paygreenjs.attachEventListener(
            paygreenjs.Events.PAYMENT_FLOW_ONCHANGE,
            this.createPaymentFlowOnChangeListener(resolve)
          );

          window.paygreenjs.attachEventListener(
            paygreenjs.Events.FULL_PAYMENT_DONE,
            (event) => {
              console.log(event.type, event.detail)
              this.listeners.forEach(cb => {
                if (typeof cb === 'function') {
                  cb(event, response.data.id, true);
                }
              });
              this.listeners = [];
            }
          );

          window.paygreenjs.attachEventListener(
            paygreenjs.Events.PAYMENT_FAIL,
            (event) => {
              console.log(event.type, event.detail)
              this.listeners.forEach(cb => {
                if (typeof cb === 'function') {
                  cb(event, response.data.id, false);
                }
              });
              this.listeners = [];
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

          // resolve()

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


      this.listeners.push((event, paymentOrderID, success) => {
        if (success) {
          resolve(paymentOrderID);
        } else {
          reject(new Error('Try again later'));
        }
      })
      window.paygreenjs.submitPayment();
    });
  }
}
