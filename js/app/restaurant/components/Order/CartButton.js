import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import classNames from 'classnames'

import {
  selectIsFetching,
  selectIsFulfilmentTimeSlotsAvailable,
  selectIsOrderAdmin,
  selectItems,
} from '../../redux/selectors'
import OrderState from './OrderState'

class CartButton extends Component {

  render() {

    const { hasErrors, isOrderAdmin, isFulfilmentTimeSlotsAvailable, items, loading } = this.props

    const disabled = (hasErrors || !isOrderAdmin || !isFulfilmentTimeSlotsAvailable || items.length === 0 || loading)
    const btnProps = disabled ? { disabled: true } : {}

    return (<button type="submit" className={ classNames({
      'order-button': true,
      'btn': true,
      'btn-lg': true,
      'btn-primary': true,
      'disabled': disabled,
    }) }{ ...btnProps }>
      <OrderState />
    </button>)
  }
}

function mapStateToProps(state) {

  return {
    items: selectItems(state),
    loading: selectIsFetching(state),
    hasErrors: _.size(state.errors) > 0,
    isFulfilmentTimeSlotsAvailable: selectIsFulfilmentTimeSlotsAvailable(state),
    isOrderAdmin: selectIsOrderAdmin(state),
  }
}

export default connect(mapStateToProps)(withTranslation()(CartButton))
