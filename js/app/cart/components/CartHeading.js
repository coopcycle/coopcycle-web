import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import { toggleMobileCart } from '../redux/actions'

class CartHeading extends React.Component {

  constructor(props) {
    super(props)
    this.state = {}
  }

  onCartHeadingSubmitClick(e) {
    // Avoid opening mobile cart when click submit button
    e.stopPropagation()
  }

  renderHeadingLeft(warningAlerts, dangerAlerts) {
    const { loading } = this.props

    if (loading) {
      return (
        <i className="fa fa-spinner fa-spin"></i>
      )
    }

    if (warningAlerts.length > 0 || dangerAlerts.length > 0) {
      return (
        <i className="fa fa-warning"></i>
      )
    }

    return (
      <i className="fa fa-check"></i>
    )
  }

  headingTitle(warnings, errors) {
    const { loading } = this.props

    if (errors.length > 0) {
      return _.first(errors)
    }
    if (warnings.length > 0) {
      return _.first(warnings)
    }

    return !loading ? this.props.t('CART_WIDGET_BUTTON') : this.props.t('CART_TITLE')
  }

  render() {

    const { items, loading, warningAlerts, dangerAlerts } = this.props

    const headingClasses = ['panel-heading', 'cart-heading']
    if (warningAlerts.length > 0 || dangerAlerts.length > 0) {
      headingClasses.push('cart-heading--warning')
    }

    if (!loading && items.length > 0 && warningAlerts.length === 0 && dangerAlerts.length === 0) {
      headingClasses.push('cart-heading--success')
    }

    return (
      <div className={ headingClasses.join(' ') } onClick={ () => this.props.toggleMobileCart() }>
        <span className="cart-heading__left">
          { this.renderHeadingLeft(warningAlerts, dangerAlerts) }
        </span>
        <span className="cart-heading--title">{ this.props.t('CART_TITLE') }</span>
        <span className="cart-heading--title-or-errors">
          { this.headingTitle(warningAlerts, dangerAlerts) }
        </span>
        <span className="cart-heading__right">
          <i className={ this.props.isMobileCartVisible ? 'fa fa-chevron-up' : 'fa fa-chevron-down' }></i>
        </span>
        <button type="submit" className="cart-heading__button" onClick={ this.onCartHeadingSubmitClick.bind(this)  }>
          <i className="fa fa-arrow-right "></i>
        </button>
      </div>
    )
  }

}

function mapStateToProps (state) {

  const warningAlerts = []
  const dangerAlerts = []

  if (state.errors) {

    // We don't display the error when restaurant has changed
    const errors = _.pickBy(state.errors, (value, key) => key !== 'restaurant')

    _.forEach(errors, (errors, key) => {
      if (key === 'shippingAddress') {
        errors.forEach(error => dangerAlerts.push(error.message))
      } else {
        errors.forEach(error => warningAlerts.push(error.message))
      }
    })
  }

  let items = state.cart.items
  if (state.cart.restaurant.id !== state.restaurant.id) {
    items = []
  }

  return {
    isMobileCartVisible: state.isMobileCartVisible,
    loading: state.isFetching,
    dangerAlerts,
    warningAlerts,
    items,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    toggleMobileCart: () => dispatch(toggleMobileCart()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(CartHeading))
