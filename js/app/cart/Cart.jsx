import React from 'react';
import PropTypes from 'prop-types';
import _ from 'lodash';
import Sticky from 'react-stickynode';

import CartItem from './CartItem.jsx';
import DatePicker from './DatePicker.jsx';
import AddressPicker from "../address/AddressPicker.jsx";
import CartTop from "./CartTop.jsx"
import { geocodeByAddress } from 'react-places-autocomplete';

import numeral  from 'numeral';
import 'numeral/locales'

const locale = $('html').attr('lang')

numeral.locale(locale)

class Cart extends React.Component
{
  constructor(props) {
    super(props);

    let { items, deliveryDate, streetAddress, isMobileCart, geohash } = this.props;

    this.state = {
      items,
      toggled: !isMobileCart,
      date: deliveryDate,
      address: streetAddress,
      geohash: geohash,
      errors: {}
    }

    this.onDateChange = this.onDateChange.bind(this)
    this.onAddressChange = this.onAddressChange.bind(this)
    this.onAddressSelect = this.onAddressSelect.bind(this)
    this.onHeaderClick = this.onHeaderClick.bind(this)
    this.computeCartTotal = this.computeCartTotal.bind(this)
  }

  onHeaderClick() {
    this.setState({'toggled': !this.state.toggled})
  }

  computeCartTotal() {

    // Sum delivery price when there is at least one item
    if (this.state.items.length === 0) {
      return 0
    }

    const { flatDeliveryPrice } = this.props.restaurant

    const itemsTotalPrice = _.reduce(this.state.items, function(memo, item) {
      return memo + item.total;
    }, 0)

    return (itemsTotalPrice + flatDeliveryPrice).toFixed(2)
  }

  resolveAddToCartURL() {
    const { addToCartURL, restaurant } = this.props

    return addToCartURL.replace('__RESTAURANT_ID__', restaurant.id)
  }

  resolveRemoveFromCartURL(itemKey) {
    const { removeFromCartURL, restaurant } = this.props

    return removeFromCartURL
      .replace('__RESTAURANT_ID__', restaurant.id)
      .replace('__ITEM_KEY__', itemKey)
  }

  removeItem(item) {
    $.ajax({
      url: this.resolveRemoveFromCartURL(item.props.itemKey),
      type: 'DELETE',
    })
    .then(res => this.handleAjaxResponse(res))
    .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  addMenuItemById(id, modifiers) {
    $.post(this.resolveAddToCartURL(), {
      selectedItemData: {
        menuItemId: id,
        modifiers: modifiers
      },
      date: this.state.date
    })
    .then(res => this.handleAjaxResponse(res))
    .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  handleAjaxResponse(res) {
    this.setState({ items: res.cart.items, errors: res.errors })
    let total = this.computeCartTotal()
    this.props.onCartChange(total)
  }

  onDateChange(dateString) {
    $.post(this.resolveAddToCartURL(), {
      date: dateString,
    })
    .then(res => this.handleAjaxResponse(res))
    .fail(e => this.handleAjaxResponse(e.responseJSON))
  }

  onAddressChange (geohash, addressString) {
    geocodeByAddress(addressString).then((results) => {
      if (results.length === 1) {

        // format Google's places format to a clean dict
        let place = results[0],
          addressDict = {},
          lat = place.geometry.location.lat(),
          lng = place.geometry.location.lng();

        place.address_components.forEach(function (item) {
          addressDict[item.types[0]] = item.long_name
        });

        addressDict.streetAddress = addressDict.street_number ? addressDict.street_number + ' ' + addressDict.route : addressDict.route;

        let address = {
          'latitude': lat,
          'longitude': lng,
          'addressCountry': addressDict.country || '',
          'addressLocality': addressDict.locality || '',
          'addressRegion': addressDict.administrative_area_level_1 || '',
          'postalCode': addressDict.postal_code || '',
          'streetAddress': addressDict.streetAddress || '',
        }

        $.post(this.resolveAddToCartURL(), {
          date: this.state.date, // do not remove this line (used to set `date` on `componentDidMount` event)
          address: address
        })
        .then(res => this.handleAjaxResponse(res))
        .fail(e => this.handleAjaxResponse(e.responseJSON))

      } else {
        throw new Error('More than 1 place returned with value ' + this.props.address)
      }
    }).catch((err) => { console.log(err) });
  }

  onAddressSelect(geohash, address) {
    this.onAddressChange(geohash, address)
  }

  componentDidMount() {
    // FIXME this.props.geohash & this.props.streetAddress may be empty
    this.onAddressChange(this.props.geohash, this.props.streetAddress)
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

  render() {

    let { items, toggled, errors, date, geohash, address } = this.state,
        cartContent,
        { isMobileCart, availabilities, validateCartURL } = this.props,
        { flatDeliveryPrice } = this.props.restaurant,
        cartTitleKey = isMobileCart ? 'cart.widget.button' : 'Cart'

    if (items.length > 0) {
      let cartItemComponents = items.map((item, key) => {
        return (
          <CartItem
            cart={this}
            id={item.id}
            key={key}
            itemKey={item.key}
            name={item.name}
            total={item.total}
            quantity={item.quantity}
            modifiersDescription={item.modifiersDescription}
          />
        )
      })

      cartContent = (
        <div className="list-group">{cartItemComponents}</div>
      )
    } else {
      cartContent = ( <div className="alert alert-warning">Votre panier est vide</div> )
    }

    let itemsTotalPrice = _.reduce(items, function(memo, item) {
      return memo + (item.total);
    }, 0),
        itemCount = _.reduce(items, function(memo, item) {
      return memo + (item.quantity);
    }, 0),
        total = items.length > 0 ? ( itemsTotalPrice + flatDeliveryPrice ) : 0

    const warningAlerts = []
    const dangerAlerts = []

    if (!address) {
      warningAlerts.push('Veuillez sélectionner une adresse')
    }

    if (errors) {
      if (errors.total) {
        errors.total.forEach((message, key) => warningAlerts.push(message))
      }
      if (errors.address) {
        errors.address.forEach((message, key) => dangerAlerts.push(message))
      }
      if (errors.date) {
        errors.date.forEach((message, key) => dangerAlerts.push(message))
      }
      if (errors.item) {
        errors.item.forEach((message, key) => dangerAlerts.push(message))
      }
    }

    var btnClasses = ['btn', 'btn-block', 'btn-primary'];

    if (items.length === 0 || !address || _.size(errors) > 0) {
      btnClasses.push('disabled')
    }

    var panelClasses = ['panel', 'panel-default', 'cart-wrapper'];
    if (toggled) {
      panelClasses.push('cart-wrapper--show')
    }

    return (
      <Sticky enabled={!isMobileCart} top={ 30 }>
        <div className={ panelClasses.join(' ') }>
          <div className="panel-heading cart-heading" onClick={ this.onHeaderClick }>
              <span className="cart-heading--items">{ itemCount }</span>
              <span className="cart-heading--total"><i className={ toggled ? "fa fa-chevron-up" : "fa fa-chevron-down"}></i></span>
              { this.props.i18n[cartTitleKey] }
          </div>
          <div className="panel-body">
            { this.renderWarningAlerts(warningAlerts) }
            { this.renderDangerAlerts(dangerAlerts) }
            <div className="cart">
              <AddressPicker
                preferredResults={[]}
                address={address}
                geohash={geohash}
                onPlaceChange={this.onAddressSelect}
              />
              <hr />
              <DatePicker
                availabilities={availabilities}
                value={date}
                onChange={this.onDateChange} />
              <hr />
              {cartContent}
              <hr />
              {
                items.length > 0 && (
                  <div>
                    <span>Prix de la course </span>
                    <strong className="pull-right">{ numeral(flatDeliveryPrice).format('0,0.00 $') }</strong>
                  </div>
                )
              }
              <div>
                <span>Total</span>
                <strong className="pull-right">{ numeral(total).format('0,0.00 $') }</strong>
              </div>
              <hr />
              <a href={validateCartURL} className={btnClasses.join(' ')}>Commander</a>
            </div>
          </div>
        </div>
      </Sticky>
    );
  }
}


Cart.propTypes = {
  items: PropTypes.arrayOf(PropTypes.object),
  streetAddress: PropTypes.string.isRequired,
  deliveryDate: PropTypes.string.isRequired,
  availabilities: PropTypes.arrayOf(PropTypes.string).isRequired,
  validateCartURL: PropTypes.string.isRequired,
  removeFromCartURL: PropTypes.string.isRequired,
  addToCartURL: PropTypes.string.isRequired,
  isMobileCart: PropTypes.bool.isRequired,
  restaurant: PropTypes.object.isRequired
}


module.exports = Cart
