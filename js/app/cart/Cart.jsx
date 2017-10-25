import React from 'react';
import _ from 'underscore';
import CartItem from './CartItem.jsx';
import DatePicker from './DatePicker.jsx';
import Sticky from 'react-stickynode';

class Cart extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      items: props.items,
      date: props.deliveryDate
    };
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
    $.post(this.props.addToCartURL, {
      date: dateString
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
      <Sticky enabled={true} top={ 30 }>
        <div className="panel panel-default">
          <div className="panel-heading">
            <h3 className="panel-title">{ this.props.i18n['Cart'] }</h3>
          </div>
          <div className="panel-body">
            <div className="cart">
              <DatePicker
                availabilities={this.props.availabilities}
                value={this.props.deliveryDate}
                onChange={(date) => this.onDateChange(date)} />
              <hr />
              {cartContent}
              <strong>Total {sum} €</strong>
              <hr />
              <a href={this.props.validateCartURL} className={btnClasses.join(' ')}>Commander</a>
            </div>
          </div>
        </div>
      </Sticky>
    );
  }
}

module.exports = Cart;
