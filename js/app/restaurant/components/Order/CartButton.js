import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import classNames from 'classnames'

import {
  selectIsFetching,
  selectIsOrderingAvailable,
  selectItems,
} from '../../redux/selectors'
import OrderState from './OrderState'

class CartButton extends Component {

  render() {

    const { hasErrors, isOrderingAvailable, items, loading } = this.props

    const disabled = (hasErrors || !isOrderingAvailable || items.length === 0 || loading)
    const btnProps = disabled ? { disabled: true } : {}

    return (<button type="submit" className={ classNames({
      'mt-4': true,
      'btn': true,
      'btn-lg': true,
      'btn-block': true,
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
    isOrderingAvailable: selectIsOrderingAvailable(state),
  }
}

export default connect(mapStateToProps)(withTranslation()(CartButton))
