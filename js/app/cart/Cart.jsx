import React from 'react'
import { findDOMNode } from 'react-dom'
import PropTypes from 'prop-types'
import _ from 'lodash'
import Sticky from 'react-stickynode'
import i18n from '../i18n'

import CartItem from './CartItem.jsx'
import DatePicker from './DatePicker.jsx'
import AddressPicker from "../components/AddressPicker.jsx"
import Modal from 'react-modal';

let timeoutID = null

Modal.setAppElement(document.getElementById('cart'));

class Cart extends React.Component
{
  constructor(props) {
    super(props);

    let { items, total, itemsTotal, adjustments, deliveryDate, streetAddress, isMobileCart, geohash } = this.props;

    this.state = {
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
      modalIsOpen: false,
      modalHeadingText: '',
    }

    this.onAddressChange = this.onAddressChange.bind(this)
    this.onAddressSelect = this.onAddressSelect.bind(this)
    this.onHeaderClick = this.onHeaderClick.bind(this)
  }

  componentDidUpdate(prevProps, prevState) {

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

  getCart() {
    const { address, date } = this.state

    return { address, date }
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

    if (errors.hasOwnProperty('shippingAddress')) {
      // We trigger the modal only when the address was not set
      const { address } = this.state
      if (!address) {
        newState = {
          ...newState,
          modalIsOpen: true,
          modalHeadingText: _.first(errors.shippingAddress)
        }
      }
    }

    this.setState(newState)
  }

  setLoading(loading) {
    this.setState({ loading })
  }

  onHeaderClick() {
    const toggled = !this.state.toggled
    window._paq.push(['trackEvent', 'Checkout', toggled ? 'openMobileCart' : 'closeMobileCart'])
    this.setState({ toggled })
  }

  onAddressChange(geohash, addressString) {
    this.props.onAddressChange(addressString)
  }

  onAddressSelect(geohash, address) {

    const { modalIsOpen } = this.state

    let newState = { address }
    if (true === modalIsOpen) {
      newState = { ...newState, modalIsOpen: false }
    }

    this.setState(newState)
    this.onAddressChange(geohash, address)
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

    const { toggled, initialized } = this.state
    const { validateCartURL } = this.props

    const headingClasses = ['panel-heading', 'cart-heading']
    if (warningAlerts.length > 0 || dangerAlerts.length > 0) {
      headingClasses.push('cart-heading--warning')
    }

    if (initialized && warningAlerts.length === 0 && dangerAlerts.length === 0) {
      headingClasses.push('cart-heading--success')
    }

    const onButtonClick = e => {
      window.location.href = validateCartURL
      e.stopPropagation()
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
          <i className={ toggled ? "fa fa-chevron-up" : "fa fa-chevron-down" }></i>
        </span>
        <button onClick={ onButtonClick } className="cart-heading__button">
          <i className="fa fa-arrow-right "></i>
        </button>
      </div>
    )
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
    const { initialized } = this.state

    if (errors.length > 0) {
      return _.first(errors)
    }
    if (warnings.length > 0) {
      return _.first(warnings)
    }

    return initialized ? i18n.t('CART_WIDGET_BUTTON') : i18n.t('CART_TITLE')
  }

  afterOpenModal() {
    window._paq.push(['trackEvent', 'Checkout', 'openModal'])
    setTimeout(() => this.modalAddressPicker.setFocus(), 250);
  }

  closeModal() {
    this.setState({ modalIsOpen: false });
  }

  render() {

    let { items, toggled, errors, date, geohash, address, loading, modalIsOpen, modalHeadingText } = this.state,
        cartContent,
        { isMobileCart, availabilities, validateCartURL } = this.props,
        cartTitleKey = isMobileCart ? i18n.t('CART_WIDGET_BUTTON') : i18n.t('CART_TITLE')

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
      cartContent = ( <div className="alert alert-warning">{i18n.t("CART_EMPTY")}</div> )
    }

    const warningAlerts = []
    const dangerAlerts = []

    if (errors) {
      if (errors.total) {
        errors.total.forEach((message, key) => warningAlerts.push(message))
      }
      if (errors.shippingAddress) {
        errors.shippingAddress.forEach((message, key) => dangerAlerts.push(message))
      }
      if (errors.shippedAt) {
        errors.shippedAt.forEach((message, key) => dangerAlerts.push(message))
      }
      if (errors.items) {
        errors.items.forEach((message, key) => dangerAlerts.push(message))
      }
    }

    var btnClasses = ['btn', 'btn-block', 'btn-primary'];

    if (items.length === 0 || !address || _.size(errors) > 0 || loading) {
      btnClasses.push('disabled')
    }

    var panelClasses = ['panel', 'panel-default', 'cart-wrapper'];
    if (toggled) {
      panelClasses.push('cart-wrapper--show')
    }

    const addressPickerProps = {
      onPlaceChange: this.onAddressSelect,
      /* https://reactjs.org/blog/2018/06/07/you-probably-dont-need-derived-state.html#recommendation-fully-uncontrolled-component-with-a-key */
      key: address
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
                availabilities={availabilities}
                value={date}
                onChange={this.props.onDateChange} />
              <hr />
              { cartContent }
              { this.renderTotal() }
              <hr />
              <a href={validateCartURL} className={btnClasses.join(' ')}>
                <span>{ loading && <i className="fa fa-spinner fa-spin"></i> }</span>  <span>{ i18n.t('CART_WIDGET_BUTTON') }</span>
              </a>
            </div>
          </div>
        </div>
        <Modal
          isOpen={ modalIsOpen }
          onAfterOpen={ this.afterOpenModal.bind(this) }
          onRequestClose={ this.closeModal.bind(this) }
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
        </Modal>
      </Sticky>
    );
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

module.exports = Cart
