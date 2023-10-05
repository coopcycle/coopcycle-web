import React from 'react'
import {connect} from 'react-redux'
import {withTranslation} from 'react-i18next'
import _ from 'lodash'

import CartItem from './CartItem'
import {removeItem, updateItemQuantity} from '../redux/actions'
import {selectItems, selectItemsGroups, selectPlayersGroups, selectShowPricesTaxExcluded} from '../redux/selectors'
import classNames from "classnames";

class CartItems extends React.Component {

  constructor(props) {
    super(props);
    this.state = {
      tabSelected: null
    }
  }

shouldComponentUpdate(nextProps, nextState) {
    if (nextProps.showTabs && nextState.tabSelected !== null) {
      if(!Object.keys(nextProps.playersGroups).includes(nextState.tabSelected)) {
        this.setState({tabSelected: null})
        return false
      }
    }
    return true
}

  _onChangeQuantity(itemID, quantity) {
    if (!_.isNumber(quantity)) {
      return
    }

    if (quantity === 0) {
      this.props.removeItem(itemID)
      return
    }

    this.props.updateItemQuantity(itemID, quantity)
  }

  _onRemoveItem(itemID) {
    this.props.removeItem(itemID)
  }

  renderItems(items) {

    if (this.state.tabSelected &&
      this.props.playersGroups[this.state.tabSelected] !== undefined) {
      items = this.props.playersGroups[this.state.tabSelected]
    }

    // Make sure items are always in the same order
    // We order them by id asc
    items.sort((a, b) => a.id - b.id)

    return (
      <div>
        { items.map((item) => (
          <CartItem
            key={ `cart-item-${item.id}` }
            id={ item.id }
            name={ item.name }
            total={ item.total }
            quantity={ item.quantity }
            adjustments={ item.adjustments }
            showPricesTaxExcluded={ this.props.showPricesTaxExcluded }
            onChangeQuantity={ quantity => this._onChangeQuantity(item.id, quantity) }
            onClickRemove={ () => this._onRemoveItem(item.id) } />
        )) }
      </div>
    )
  }

  renderTabs(items) {
    return <ul className="nav nav-tabs">
      {_.map(items, (item, playerID) =>  (
          <li key={playerID} onClick={(e) => {
            e.preventDefault()
            this.setState({tabSelected: playerID})}
          } className={classNames({
            active: playerID === this.state.tabSelected
          })}>
            <a href="#">{playerID}</a>
          </li>
        )) }
    </ul>
  }

  render() {

    if (this.props.items.length === 0) {
      return (
        <div className="alert alert-warning">{ this.props.t("CART_EMPTY") }</div>
      )
    }

    if (_.size(this.props.itemsGroups) > 1) {

      return (
        <div className="cart__items">
          { _.map(this.props.itemsGroups, (items, title) => {
            return (
              <React.Fragment key={ title }>
                <h5 className="text-muted">{ title }</h5>
                { this.renderItems(items) }
              </React.Fragment>
            )
          })}
        </div>
      )
    }

    return (
      <div>
        { this.props.showTabs && this.renderTabs(this.props.playersGroups) }
      <div className="cart__items">
        { this.renderItems(this.props.items) }
      </div>
      </div>
    )
  }
}

function mapStateToProps (state) {

  const items = selectItems(state)
  const itemsGroups = selectItemsGroups(state)
  const playersGroups = selectPlayersGroups(state)

  return {
    items,
    itemsGroups,
    playersGroups,
    showPricesTaxExcluded: selectShowPricesTaxExcluded(state),
    showTabs: !state.isPlayer && Object.keys(playersGroups).length > 1
  }
}

function mapDispatchToProps(dispatch) {
  return {
    removeItem: itemID => dispatch(removeItem(itemID)),
    updateItemQuantity: (itemID, quantity) => dispatch(updateItemQuantity(itemID, quantity)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(CartItems))
