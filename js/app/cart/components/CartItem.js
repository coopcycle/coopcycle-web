import React from 'react'
import { connect } from 'react-redux'
import { InputNumber } from 'antd'

const truncateText = text => {
  if (text.length > 24) {
    return text.substring(0, 23) + '…'
  }

  return text
}

class CartItem extends React.Component {

  _setInputNumberRef(el) {
    this.inputNumber = el
  }

  componentDidUpdate(prevProps) {
    if (!this.props.loading && prevProps.loading) {
      // https://github.com/facebook/react/issues/9142
      this.inputNumber.focus()
      this.inputNumber.blur()
    }
  }

  renderAdjustments() {
    const { adjustments } = this.props

    if (adjustments.hasOwnProperty('menu_item_modifier')) {
      return (
        <div className="cart__item__adjustments">
          { adjustments.menu_item_modifier.map(adjustment =>
            <div key={ adjustment.id }>
              <small>{ truncateText(adjustment.label) }</small>
              { adjustment.amount > 0 && (
                <small> (+{ (adjustment.amount / 100).formatMoney(2, window.AppData.currencySymbol) })</small>
              )}
            </div>
          )}
        </div>
      )
    }
  }

  render() {

    return (
      <div className="cart__item">
        <div className="cart__item__content">
          <div className="cart__item__content__left">
            <InputNumber min={ 0 } defaultValue={ 1 } value={ this.props.quantity }
              disabled={ this.props.loading }
              onChange={ value => this.props.onChangeQuantity(value) }
              ref={ this._setInputNumberRef.bind(this) } />
          </div>
          <div className="cart__item__content__body">
            <span>{ truncateText(this.props.name) }</span>
            { this.renderAdjustments() }
          </div>
          <div className="cart__item__content__right">
            <span>{ (this.props.total / 100).formatMoney(2, window.AppData.currencySymbol) }</span>
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps (state) {

  return {
    loading: state.isFetching,
  }
}

export default connect(mapStateToProps)(CartItem)
