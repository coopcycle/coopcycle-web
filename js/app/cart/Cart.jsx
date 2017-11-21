import React from 'react';
import PropTypes from 'prop-types';
import _ from 'underscore';
import Sticky from 'react-stickynode';

import CartItem from './CartItem.jsx';
import DatePicker from './DatePicker.jsx';
import AddressPicker from "../address/AddressPicker.jsx";
import CartTop from "./CartTop.jsx"
import CartPortal from "./CartPortal.jsx"
import { geocodeByAddress } from 'react-places-autocomplete';

class Cart extends React.Component
{
  constructor(props) {
    super(props);

    let { items, deliveryDate, streetAddress, addressId, isMobileCart, sameTopCart } = this.props;

    if (this.props.sameTopCart) {
      this.removeSessionTopCart();
    }

    this.state = {
      items,
      sameTopCart: sameTopCart,
      toggled: !isMobileCart,
      date: deliveryDate,
      address: {streetAddress, addressId: addressId}
    }

    this.onDateChange = this.onDateChange.bind(this)
    this.onAddressSelect = this.onAddressSelect.bind(this)
    this.onHeaderClick = this.onHeaderClick.bind(this)
    this.removeSessionTopCart = this.removeSessionTopCart.bind(this)
  }

  onHeaderClick () {
    this.setState({'toggled': !this.state.toggled})
  }

  setSameTopCartTrue () {
    this.setState({'sameTopCart': true})
  }

  removeItem(item) {
    let removeFromCartUrl = this.props.removeFromCartURL.replace('__ITEM_KEY__', item.props.itemKey);
    $.ajax({
      url: removeFromCartUrl,
      type: 'DELETE',
    }).then((cart) => {
      this.setState({items: cart.items});
    });
  }

  addMenuItemById(id, modifiers) {
    $.post(this.props.addToCartURL, {
      selectedItemData: {
        menuItemId: id,
        modifiers: modifiers
      },
      date: this.state.date
    }).then((cart) => {
      this.setState({items: cart.items});
    });
  }

  onDateChange(dateString) {
    // TODO : clean this, it feels hacky
    // the way it works for now :
    // - if date passed with props, will send at first item added
    // - if date not set with props, will send at the re-render triggered by first item added
    $.post(this.props.addToCartURL, {
      date: dateString
    });
  }

  onAddressSelect(address) {
    // TODO : enable address input on Cart
    return;
  }

  componentDidMount() {
    // we can set the address on the cart here, because we are sure the distance is valid for the restaurant
    geocodeByAddress(this.props.streetAddress).then((results) => {
      if ( results.length === 1) {

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

        $.post(this.props.addToCartURL, {
          date: this.props.deliveryDate,
          address: address
        });

      } else {
        throw new Error('More than 1 place returned with value ' + this.props.address)
      }
    }).catch((err) => { console.log(err) });
  }

  removeSessionTopCart() {
    document.querySelector('a.btn-default').remove();
  }

  render() {

    let { items, toggled } = this.state ,
        cartContent,
        { streetAddress, geohash, isMobileCart, deliveryDate, availabilities, validateCartURL } = this.props,
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
        );
      });

      cartContent = (
        <div className="list-group">{items}</div>
      );
    } else {
      cartContent = (
        <div className="alert alert-warning">Votre panier est vide</div>
      );
    }

    var sum = _.reduce(this.state.items, function(memo, item) {
      return memo + (item.total);
    }, 0).toFixed(2);

    var itemCount = _.reduce(this.state.items, function(memo, item) {
      return memo + (item.quantity);
    }, 0);

    var btnClasses = ['btn', 'btn-block', 'btn-primary'];
    if (items.length === 0) {
      btnClasses.push('disabled');
    }

    var panelClasses = ['panel', 'panel-default', 'cart-wrapper'];
    if (toggled) {
      panelClasses.push('cart-wrapper--show');
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
            <div className="cart">
              <AddressPicker
                inputProps={ {disabled: true} }
                preferredResults={[]}
                address={streetAddress}
                geohash={geohash}
                onPlaceChange={this.onAddressSelect}
              />
              <hr />
              <DatePicker
                availabilities={availabilities}
                value={deliveryDate}
                onChange={this.onDateChange} />
              <hr />
              {cartContent}
              <strong>Total {sum} €</strong>
              <hr />
              <a href={validateCartURL} className={btnClasses.join(' ')}>Commander</a>
            </div>
          </div>
        </div>
        {this.state.sameTopCart ? (<CartPortal><CartTop total={sum} i18n={this.props.i18n}/></CartPortal>):(<div></div>)}
      </Sticky>
    );
  }
}


Cart.propTypes = {
  items: PropTypes.arrayOf(PropTypes.object),
  streetAddress: PropTypes.string.isRequired,
  addressId: PropTypes.number,
  deliveryDate: PropTypes.string.isRequired,
  availabilities: PropTypes.arrayOf(PropTypes.string).isRequired,
  validateCartURL: PropTypes.string.isRequired,
  removeFromCartURL: PropTypes.string.isRequired,
  addToCartURL: PropTypes.string.isRequired,
  isMobileCart: PropTypes.bool.isRequired
}


module.exports = Cart;
