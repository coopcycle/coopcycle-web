import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'

import ShippingTimeRange from '../../components/ShippingTimeRange'
import { setDateModalOpen } from '../redux/actions'

class Time extends React.Component {

  _onClick(e) {
    e.preventDefault()

    if (!this.props.loading) {
      this.props.setDateModalOpen(true)
    }
  }

  render() {

    const cssClasses = [ 'cart__time' ]
    if (!this.props.today) {
      cssClasses.push('cart__time--not-today')
    }

    return (
      <a className={ cssClasses.join(' ') } href="#" onClick={ this._onClick.bind(this) }>
        <strong className="cart__time__text">
          <ShippingTimeRange value={ this.props.shippingTimeRange } />
        </strong>
        <span className="cart__time__edit">{ this.props.t('CART_DELIVERY_TIME_EDIT') }</span>
      </a>
    )
  }
}

function mapStateToProps(state) {

  const { shippingTimeRange } = state.cart
  const { today, range } = state.times

  return {
    today,
    loading: state.isFetching,
    shippingTimeRange: shippingTimeRange || range,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    setDateModalOpen: isOpen => dispatch(setDateModalOpen(isOpen)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Time))
