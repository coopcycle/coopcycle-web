import React from 'react'
import { findDOMNode } from 'react-dom'
import PropTypes from 'prop-types'
import _ from 'lodash'
import Sticky from 'react-stickynode'
import Modal from 'react-modal'
import md5 from 'locutus/php/strings/md5'

import i18n from '../i18n'
import CartItem from './CartItem.jsx'
import DatePicker from './DatePicker.jsx'
import AddressPicker from '../components/AddressPicker.jsx'

let timeoutID = null

Modal.setAppElement(document.getElementById('cart'))

class Cart extends React.Component
{
  constructor(props) {
    super(props)

    const {
      availabilities,
      items,
      total,
      itemsTotal,
      adjustments,
      deliveryDate,
      streetAddress,
      isMobileCart,
      geohash
    } = this.props

    this.state = {
      availabilities,
      items,
      total,
      itemsTotal,
      adjustments,
      toggled: !isMobileCart,
      date: deliveryDate,
      address: streetAddress,
      geohash: geohash,
      errors: {},
      loading: false,
      initialized: false,
      addressModalIsOpen: false,
      modalHeadingText: '',
      restaurantModalIsOpen: false,
    }

    this.onAddressSelect = this.onAddressSelect.bind(this)
    this.onHeaderClick = this.onHeaderClick.bind(this)
  }

  componentDidUpdate() {

    const { errors, toggled } = this.state
    const { isMobileCart } = this.props

    // Do nothing on desktop devices
    if (!isMobileCart) {
      return
    }

    // Stop animation when cart is open
    if (toggled) {
      clearTimeout(timeoutID)
      timeoutID = null
      return
    }

    // Stop animation if there are no errors anymore
    if (_.size(errors) === 0) {
      clearTimeout(timeoutID)
      timeoutID = null
      return
    }

    // Do nothing if the animation is already running
    if (timeoutID) {
      return
    }

    const headingRight = findDOMNode(this.refs.headingRight)

    const rippleClass = 'cart-heading__right--ripple'

    const toggleClass = () => {
      if (headingRight.classList.contains(rippleClass)) {
        headingRight.classList.remove(rippleClass)
      } else {
        headingRight.classList.add(rippleClass)
      }
    }

    const toggleClassWithTimeout = () => {
      toggleClass()
      timeoutID = setTimeout(toggleClassWithTimeout, 2000)
    }

    toggleClassWithTimeout()
  }

  setAvailabilities(availabilities) {
    this.setState({ availabilities })
  }

  setCart(cart) {

    const { initialized } = this.state

    if (!initialized) {
      cart = { ...cart, initialized: true }
    }

    this.setState(cart)
  }

  setErrors(errors) {
    let newState = { errors }

    if (errors.hasOwnProperty('restaurant')) {
      newState = {
        ...newState,
        restaurantModalIsOpen: true,
      }
    }

    if (errors.hasOwnProperty('shippingAddress')) {
      // We trigger the modal only when the address was not set
      const { address } = this.state
      if (!address) {
        newState = {
          ...newState,
          addressModalIsOpen: true,
          modalHeadingText: _.first(errors.shippingAddress)
        }
      }
    }

    this.setState(newState)
  }

  setLoading(loading) {

    let newState = { loading }
    if (loading) {
      newState = {
        ...newState,
        errors: {}
      }
    }

    this.setState(newState)
  }

  isLoading() {

    const { loading } = this.state

    return loading
  }

  onHeaderClick() {
    const toggled = !this.state.toggled
    window._paq.push(['trackEvent', 'Checkout', toggled ? 'openMobileCart' : 'closeMobileCart'])
    this.setState({ toggled })
  }

  onAddressSelect(value, address) {

    const { addressModalIsOpen } = this.state

    let newState = { address: value }
    if (true === addressModalIsOpen) {
      newState = { ...newState, addressModalIsOpen: false }
    }

    this.setState(newState)
    this.props.onAddressChange(address)
  }

  renderWarningAlerts(messages) {
    return messages.map((message, key) => (
      <div key={ key } className="alert alert-warning">{ message }</div>
    ))
  }

  renderDangerAlerts(messages) {
    return messages.map((message, key) => (
      <div key={ key } className="alert alert-danger">{ message }</div>
    ))
  }

  renderAdjustments() {
    const { adjustments } = this.state
    if (adjustments.hasOwnProperty('delivery')) {
      return (
        <div>
          { adjustments.delivery.map(adjustment =>
            <div key={ adjustment.id }>
              <span>{ adjustment.label }</span>
              <strong className="pull-right">{ (adjustment.amount / 100).formatMoney(2, window.AppData.currencySymbol) }</strong>
            </div>
          )}
        </div>
      )
    }
  }

  renderTotal() {
    const { total, itemsTotal } = this.state

    if (itemsTotal > 0) {
      return (
        <div>
          <hr />
          <div>
            <span>{ i18n.t('CART_TOTAL_PRODUCTS') }</span>
            <strong className="pull-right">{ (itemsTotal / 100).formatMoney(2, window.AppData.currencySymbol) }</strong>
          </div>
          { this.renderAdjustments() }
          <div>
            <span>{ i18n.t('CART_TOTAL') }</span>
            <strong className="pull-right">{ (total / 100).formatMoney(2, window.AppData.currencySymbol) }</strong>
          </div>
        </div>
      )
    }
  }

  renderHeading(warningAlerts, dangerAlerts) {

    const { toggled, initialized, loading } = this.state

    const headingClasses = ['panel-heading', 'cart-heading']
    if (warningAlerts.length > 0 || dangerAlerts.length > 0) {
      headingClasses.push('cart-heading--warning')
    }

    if (initialized && !loading && warningAlerts.length === 0 && dangerAlerts.length === 0) {
      headingClasses.push('cart-heading--success')
    }

    return (
      <div className={ headingClasses.join(' ') } onClick={ this.onHeaderClick }>
        <span className="cart-heading__left">
          { this.renderHeadingLeft(warningAlerts, dangerAlerts) }
        </span>
        <span className="cart-heading--title">{ i18n.t('CART_TITLE') }</span>
        <span className="cart-heading--title-or-errors">
          { this.headingTitle(warningAlerts, dangerAlerts) }
        </span>
        <span className="cart-heading__right" ref="headingRight">
          <i className={ toggled ? 'fa fa-chevron-up' : 'fa fa-chevron-down' }></i>
        </span>
        <button type="submit" className="cart-heading__button" onClick={ this.onCartHeadingSubmitClick.bind(this) }>
          <i className="fa fa-arrow-right "></i>
        </button>
      </div>
    )
  }

  onCartHeadingSubmitClick(e) {
    // Avoid opening mobile cart when click submit button
    e.stopPropagation()
  }

  renderHeadingLeft(warningAlerts, dangerAlerts) {
    const { loading } = this.state

    if (loading) {
      return (
        <i className="fa fa-spinner fa-spin"></i>
      )
    }

    if (warningAlerts.length > 0 || dangerAlerts.length > 0) {
      return (
        <i className="fa fa-warning"></i>
      )
    }

    return (
      <i className="fa fa-check"></i>
    )
  }

  headingTitle(warnings, errors) {
    const { initialized, loading } = this.state

    if (errors.length > 0) {
      return _.first(errors)
    }
    if (warnings.length > 0) {
      return _.first(warnings)
    }

    return (initialized && !loading) ? i18n.t('CART_WIDGET_BUTTON') : i18n.t('CART_TITLE')
  }

  afterOpenAddressModal() {
    window._paq.push(['trackEvent', 'Checkout', 'openModal', 'enterAddress'])
    setTimeout(() => this.modalAddressPicker.setFocus(), 250)
  }

  closeAddressModal() {
    this.setState({ addressModalIsOpen: false })
  }

  afterOpenRestaurantModal() {
    window._paq.push(['trackEvent', 'Checkout', 'openModal', 'changeRestaurant'])
  }

  closeRestaurantModal() {
    this.setState({ restaurantModalIsOpen: false })
  }

  onClickCartReset() {
    this.closeRestaurantModal()
    this.props.onClickCartReset()
  }

  render() {

    const { isMobileCart } = this.props

    let {
      availabilities,
      items,
      toggled,
      errors,
      date,
      geohash,
      address,
      loading,
      addressModalIsOpen,
      modalHeadingText,
      restaurantModalIsOpen,
    } = this.state

    let cartContent
    if (items.length > 0) {
      let cartItemComponents = items.map((item, key) => {
        return (
          <CartItem
            id={item.id}
            key={key}
            name={item.name}
            total={item.total}
            quantity={item.quantity}
            adjustments={item.adjustments}
            onClickRemove={ () => this.props.onRemoveItem(item) } />
        )
      })

      cartContent = (
        <div className="cart__items">{cartItemComponents}</div>
      )
    } else {
      cartContent = (
        <div className="alert alert-warning">{i18n.t('CART_EMPTY')}</div>
      )
    }

    const warningAlerts = []
    const dangerAlerts = []

    if (errors) {
      // We don't display the error when restaurant has changed
      errors = _.pickBy(errors, (value, key) => key !== 'restaurant')

      _.forEach(errors, (messages, key) => {
        if (key === 'shippingAddress') {
          messages.forEach((message) => dangerAlerts.push(message))
        } else {
          messages.forEach((message) => warningAlerts.push(message))
        }
      })
    }

    var btnClasses = ['btn', 'btn-block', 'btn-primary']
    let btnProps = {}

    if (items.length === 0 || !address || _.size(errors) > 0 || loading) {
      btnClasses.push('disabled')
      btnProps = {
        ...btnProps,
        disabled: true
      }
    }

    var panelClasses = ['panel', 'panel-default', 'cart-wrapper']
    if (toggled) {
      panelClasses.push('cart-wrapper--show')
    }

    /**
     * In order to reset the value when moving to a different item , we can use the special React attribute called key.
     * When a key changes, React will create a new component instance rather than update the current one.
     * @see https://reactjs.org/blog/2018/06/07/you-probably-dont-need-derived-state.html#recommendation-fully-uncontrolled-component-with-a-key
     */

    const addressPickerProps = {
      onPlaceChange: this.onAddressSelect,
      key: address
    }

    const datePickerProps = {
      key: md5(availabilities.join('|'))
    }

    return (
      <Sticky enabled={!isMobileCart} top={ 30 }>
        <div className={ panelClasses.join(' ') }>
          { this.renderHeading(warningAlerts, dangerAlerts) }
          <div className="panel-body">
            <div className="cart-wrapper__messages">
              { !loading && this.renderWarningAlerts(warningAlerts) }
              { !loading && this.renderDangerAlerts(dangerAlerts) }
            </div>
            <div className="cart">
              <AddressPicker
                address={address}
                geohash={geohash}
                { ...addressPickerProps } />
              <hr />
              <DatePicker
                dateInputName={this.props.datePickerDateInputName}
                timeInputName={this.props.datePickerTimeInputName}
                availabilities={availabilities}
                value={date}
                onChange={this.props.onDateChange}
                { ...datePickerProps } />
              <hr />
              { cartContent }
              { this.renderTotal() }
              <hr />
              <button type="submit" className={btnClasses.join(' ')} { ...btnProps }>
                <span>{ loading && <i className="fa fa-spinner fa-spin"></i> }</span>  <span>{ i18n.t('CART_WIDGET_BUTTON') }</span>
              </button>
            </div>
          </div>
        </div>
        <Modal
          isOpen={ addressModalIsOpen }
          onAfterOpen={ this.afterOpenAddressModal.bind(this) }
          onRequestClose={ this.closeAddressModal.bind(this) }
          shouldCloseOnOverlayClick={ false }
          contentLabel={ i18n.t('ENTER_YOUR_ADDRESS') }
          className="ReactModal__Content--enter-address">
          <h4 className="text-center">{ modalHeadingText }</h4>
          <AddressPicker
            ref={ addressPicker => { this.modalAddressPicker = addressPicker } }
            autofocus
            address={ '' }
            geohash={ '' }
            { ...addressPickerProps } />
          <div className="text-center">
            <span className="help-block">
              { i18n.t('CART_ADDRESS_MODAL_HELP_TEXT') }
            </span>
          </div>
        </Modal>
        <Modal
          isOpen={ restaurantModalIsOpen }
          onAfterOpen={ this.afterOpenRestaurantModal.bind(this) }
          onRequestClose={ this.closeRestaurantModal.bind(this) }
          shouldCloseOnOverlayClick={ false }
          contentLabel={ i18n.t('CART_CHANGE_RESTAURANT_MODAL_LABEL') }
          className="ReactModal__Content--restaurant">
          <div className="text-center">
            <p>
              { i18n.t('CART_CHANGE_RESTAURANT_MODAL_TEXT_LINE_1') }
              <br />
              { i18n.t('CART_CHANGE_RESTAURANT_MODAL_TEXT_LINE_2') }
            </p>
          </div>
          <div className="ReactModal__Restaurant__button">
            <button type="button" className="btn btn-default" onClick={ () => this.props.onClickGoBack() }>
              { i18n.t('CART_CHANGE_RESTAURANT_MODAL_BTN_NO') }
            </button>
            <button type="button" className="btn btn-primary" onClick={ () => this.onClickCartReset() }>
              { i18n.t('CART_CHANGE_RESTAURANT_MODAL_BTN_YES') }
            </button>
          </div>
        </Modal>
      </Sticky>
    )
  }
}

Cart.propTypes = {
  items: PropTypes.arrayOf(PropTypes.object),
  total: PropTypes.number.isRequired,
  adjustments: PropTypes.object.isRequired,
  streetAddress: PropTypes.string,
  deliveryDate: PropTypes.string.isRequired,
  availabilities: PropTypes.arrayOf(PropTypes.string).isRequired,
  isMobileCart: PropTypes.bool.isRequired,
}

export default Cart
