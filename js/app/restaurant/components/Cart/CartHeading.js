import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import classNames from 'classnames'

import { toggleMobileCart } from '../../redux/actions'
import { selectItems, selectErrorMessages, selectWarningMessages } from '../../redux/selectors'

const HeadingLeftIcon = ({ loading, warnings, errors }) => {

  if (loading) {

    return (
      <i className="fa fa-spinner fa-spin"></i>
    )
  }

  if (warnings.length > 0 || errors.length > 0) {

    return (
      <i className="fa fa-warning"></i>
    )
  }

  return (
    <i className="fa fa-check"></i>
  )
}

const HeadingTitle = ({ total, warnings, errors }) => {

  if (errors.length > 0) {

    return (
      <small>{ _.first(errors) }</small>
    )
  }
  if (warnings.length > 0) {

    return (
      <small>{ _.first(warnings) }</small>
    )
  }

  return (
    <span>{ (total / 100).formatMoney() }</span>
  )
}

class CartHeading extends React.Component {

  constructor(props) {
    super(props)
    this.state = {}
  }

  onCartHeadingSubmitClick(e) {
    // Avoid opening mobile cart when click submit button
    e.stopPropagation()
  }

  render() {

    const { items, total, loading, warningAlerts, dangerAlerts } = this.props

    return (
      <div className={ classNames({
        'panel-heading': true,
        'cart-heading': true,
        'cart-heading--warning': (warningAlerts.length > 0 || dangerAlerts.length > 0),
        'cart-heading--success': (!loading && items.length > 0 && warningAlerts.length === 0 && dangerAlerts.length === 0) }) }
        onClick={ () => this.props.toggleMobileCart() }>
        <span className="cart-heading__left">
          <HeadingLeftIcon
            loading={ loading }
            warnings={ warningAlerts }
            errors={ dangerAlerts }  />
        </span>
        <span className="cart-heading--title-or-errors">
          <HeadingTitle
            total={ total }
            warnings={ warningAlerts }
            errors={ dangerAlerts }  />
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

  return {
    isMobileCartVisible: state.isMobileCartVisible,
    loading: state.isFetching,
    dangerAlerts: selectErrorMessages(state),
    warningAlerts: selectWarningMessages(state),
    items: selectItems(state),
    total: state.cart.total,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    toggleMobileCart: () => dispatch(toggleMobileCart()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(CartHeading))
