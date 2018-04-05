import React from 'react'
import numeral  from 'numeral';
import 'numeral/locales'

const locale = $('html').attr('lang')

numeral.locale(locale)

class CartTop extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      total: props.total,
      itemsTotal: props.itemsTotal,
      restaurant: props.restaurant
    }
  }

  setTotal(itemsTotal, total) {
    this.setState({ itemsTotal, total })
  }

  setRestaurant(restaurant) {
    this.setState({ restaurant })
  }

  render() {

    const { restaurantURL } = this.props
    const { restaurant, total, itemsTotal } = this.state

    let anchorURL = '#'
    if (restaurant) {
      anchorURL = restaurantURL.replace('__RESTAURANT_ID__', restaurant.id)
    }

    const amount = itemsTotal > 0 ? total : itemsTotal

    return (
      <a href={ anchorURL } className="btn btn-default navbar-btn navbar-right">
        { this.props.i18n['Cart'] } <span className="glyphicon glyphicon-shopping-cart" aria-hidden="true"></span>  { numeral(amount / 100).format('0,0.00 $') }
      </a>
    );
  }
}

export default CartTop
