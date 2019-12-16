import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import moment from 'moment'

import i18n from '../../i18n'

import { setDateModalOpen } from '../redux/actions'

moment.locale($('html').attr('lang'))

class Time extends React.Component {

  componentDidUpdate(prevProps) {

    if (this.props.timeAsText !== prevProps.timeAsText) {
      window._paq.push(['trackEvent', 'Checkout', 'timeChanged', this.props.timeAsText])
    }

    // TODO Add effect on text when date has changed
  }

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
        <strong className="cart__time__text">{ this.props.timeAsText }</strong>
        <span className="cart__time__edit">{ this.props.t('CART_DELIVERY_TIME_EDIT') }</span>
      </a>
    )
  }
}

function mapStateToProps(state) {

  const { asap, fast, today, diff } = state.times

  let timeAsText
  if (today && fast) {
    timeAsText = i18n.t('CART_DELIVERY_TIME_DIFF', { diff })
  } else {
    const time = !!state.cart.shippedAt ? state.cart.shippedAt : asap
    let fromNow = moment(time).calendar(null, { sameElse: 'LLLL' }).toLowerCase()
    timeAsText = i18n.t('CART_DELIVERY_TIME', { fromNow })
  }

  return {
    asap,
    fast,
    today,
    diff,
    loading: state.isFetching,
    timeAsText,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    setDateModalOpen: isOpen => dispatch(setDateModalOpen(isOpen)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Time))
