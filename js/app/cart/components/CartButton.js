import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

class CartButton extends Component {

  render() {

    const { hasErrors, items, loading } = this.props

    const btnClasses = ['btn', 'btn-block', 'btn-primary']
    let btnProps = {}
    if (hasErrors || items.length === 0 || loading) {
      btnClasses.push('disabled')
      btnProps = {
        ...btnProps,
        disabled: true
      }
    }

    return (
      <button type="submit" className={ btnClasses.join(' ') } { ...btnProps }>
        <span>{ this.props.loading && <i className="fa fa-spinner fa-spin"></i> }</span>  <span>{ this.props.t('CART_WIDGET_BUTTON') }</span>
      </button>
    )
  }
}

function mapStateToProps(state) {

  const { cart, restaurant } = state

  let items = cart.items
  if (cart.restaurant.id !== restaurant.id) {
    items = []
  }

  return {
    items,
    loading: state.isFetching,
    hasErrors: _.size(state.errors) > 0,
  }
}

export default connect(mapStateToProps)(withTranslation()(CartButton))
