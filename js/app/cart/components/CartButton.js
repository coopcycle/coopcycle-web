import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import classNames from 'classnames'

import { selectItems } from '../redux/selectors'

class CartButton extends Component {

  render() {

    const { hasErrors, items, loading, restaurantIsOpen } = this.props

    const disabled = (hasErrors || items.length === 0 || loading)
    const btnProps = disabled ? { disabled: true } : {}
    const label = restaurantIsOpen ? this.props.t('CART_WIDGET_BUTTON') : this.props.t('SCHEDULE_ORDER');

    return (
      <button type="submit" className={ classNames({
        'btn': true,
        'btn-lg': true,
        'btn-block': true,
        'btn-primary': true,
        'disabled': disabled }) }
        { ...btnProps }>
        <span>{ this.props.loading && <i className="fa fa-spinner fa-spin"></i> }</span>  <span>{ label }</span>
      </button>
    )
  }
}

function mapStateToProps(state) {

  return {
    items: selectItems(state),
    loading: state.isFetching,
    hasErrors: _.size(state.errors) > 0,
    restaurantIsOpen: state.restaurant.isOpen,
  }
}

export default connect(mapStateToProps)(withTranslation()(CartButton))
