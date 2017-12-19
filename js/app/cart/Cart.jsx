import React from 'react';
import PropTypes from 'prop-types';
import _ from 'underscore';
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
    this.handleAjaxErrors = this.handleAjaxErrors.bind(this)
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
    }).then((cart) => {
      this.setState({items: cart.items});
      let total = this.computeCartTotal();
      this.props.onCartChange(total);
    });
  }

  addMenuItemById(id, modifiers) {
    $.post(this.resolveAddToCartURL(), {
      selectedItemData: {
        menuItemId: id,
        modifiers: modifiers
      },
      date: this.state.date
    }).then((cart) => {
      let errors = {...this.state.errors, item: null}
      this.setState({items: cart.items, errors});
      let total = this.computeCartTotal();
      this.props.onCartChange(total);
    }).fail((e) => { this.handleAjaxErrors(e.responseJSON) })
  }

  handleAjaxErrors(responseJSON) {
    this.setState({errors: responseJSON.errors})
  }

  onDateChange(dateString) {
    $.post(this.resolveAddToCartURL(), {
      date: dateString,
    }).then(() => {
      let errors = {...this.state.errors, date: null}
      this.setState({date: dateString, errors})
    })
      .fail((e) => {this.handleAjaxErrors(e.responseJSON)})
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
        }).then(() => {
          let errors = {...this.state.errors, distance: null}
          this.setState({ addressString, errors})
        })
          .fail((e) => {
          this.handleAjaxErrors(e.responseJSON)
        })

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

  render() {

    let { items, toggled, errors, date, geohash, address } = this.state,
        cartContent,
        cartWarning,
        { isMobileCart, availabilities, validateCartURL } = this.props,
        { minimumCartAmount, flatDeliveryPrice } = this.props.restaurant,
        minimumCartString = 'Le montant minimum est de ' + minimumCartAmount + '€',
        cartTitleKey = isMobileCart ? 'cart.widget.button' : 'Cart'

    if (items.length > 0) {
      items = this.state.items.map((item, key) => {
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
        <div className="list-group">{items}</div>
      )
    } else {
      cartContent = ( <div className="alert alert-warning">Votre panier est vide</div> )
    }

    let itemsTotalPrice = _.reduce(this.state.items, function(memo, item) {
      return memo + (item.total);
    }, 0),
        itemCount = _.reduce(this.state.items, function(memo, item) {
      return memo + (item.quantity);
    }, 0),
        total = (itemsTotalPrice + flatDeliveryPrice)

    if (items.length > 0 && itemsTotalPrice < minimumCartAmount) {
      cartWarning = ( <div className="alert alert-warning">{ minimumCartString }</div> )
    } else if (!address) {
      cartWarning = ( <div className="alert alert-warning">Veuillez sélectionner une adresse.</div> )
    }

    errors = _.omit(errors, value => null === value)

    if (errors) {
      if (errors.distance) {
        cartWarning = ( <div className="alert alert-danger">Cette addresse est trop éloignée.</div> )
      } else if (errors.date) {
        cartWarning = ( <div className="alert alert-danger">Cette date de livraison est indisponible.</div> )
      } else if (errors.item) {
        cartWarning = ( <div className="alert alert-danger">{errors.item}</div> )
      }
    }

    var btnClasses = ['btn', 'btn-block', 'btn-primary'];

    if (items.length === 0 || itemsTotalPrice < minimumCartAmount || !address || _.size(errors) > 0) {
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
            {cartWarning}

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
              <div>
                <span>Prix de la course </span>
                <strong className="pull-right">{ numeral(flatDeliveryPrice).format('0,0.00 $') }</strong>
              </div>
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
