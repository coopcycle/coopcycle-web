import React from 'react';

class CartTop extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      total: props.total,
      restaurant: props.restaurant
    }
  }

  setTotal(total) {
    this.setState({ total })
  }

  setRestaurant(restaurant) {
    this.setState({ restaurant })
  }

  render() {

    const { restaurantURL } = this.props
    const { restaurant } = this.state

    let anchorURL = '#'
    if (restaurant) {
      anchorURL = restaurantURL.replace('__RESTAURANT_ID__', restaurant.id)
    }

    return (
      <a href={ anchorURL } className="btn btn-default navbar-btn navbar-right">
        { this.props.i18n['Cart'] } <span className="glyphicon glyphicon-shopping-cart" aria-hidden="true"></span>  {this.state.total} €
      </a>
    );
  }
}

export default CartTop
