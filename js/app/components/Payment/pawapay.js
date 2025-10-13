export default {
  async init() {
  },
  async mount(el, method, options, formOptions) {
    this.redirectUrl = options.pawapay.pawapay_payment_page_url;

    return new Promise((resolve) => {
      resolve()
    })
  },
  unmount() {
  },
  async createToken() {
    window.location.href = this.redirectUrl
  }
}
