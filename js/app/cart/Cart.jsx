import React from 'react';
import _ from 'underscore';
import CartItem from './CartItem.jsx';
import DatePicker from './DatePicker.jsx';

class Cart extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      items: props.items,
      date: props.date
    };
  }
  removeItem(item) {
    console.log(item.props);
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
    $.post(this.props.addToCartURL, {
      date: dateString
    }).then((cart) => {
      this.setState({items: cart.items});
    });
  }
  render() {
    var items = this.state.items.map((item, key) => {
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

    var cartContent;
    if (items.length > 0) {
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

    var btnClasses = ['btn', 'btn-block', 'btn-primary'];
    if (items.length === 0) {
      btnClasses.push('disabled');
    }

    return (
      <div className="cart">
        <DatePicker availabilities={this.props.availabilities} setDeliveryDate={(date) => this.onDateChange(date)}/>
        <hr />
        {cartContent}
        <strong>Total {sum} €</strong>
        <hr />
        <a href={this.props.validateCartURL} className={btnClasses.join(' ')}>Commander</a>
      </div>
    );
  }
}

module.exports = Cart;
