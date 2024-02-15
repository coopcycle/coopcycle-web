import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import { parsePhoneNumberFromString } from 'libphonenumber-js'
import { Formik, Field } from 'formik'
import classNames from 'classnames'

import { getCountry } from '../../../i18n'
import {
  setCurrentOrder,
  acceptOrder,
  refuseOrder,
  delayOrder,
  cancelOrder,
  fulfillOrder,
  restoreOrder,
  toggleReusablePackagings,
  updateLoopeatFormats,
} from '../redux/actions'
import { selectItemsGroups } from '../redux/selectors'

import OrderItems from './OrderItems'
import OrderTotal from './OrderTotal'
import OrderNumber from './OrderNumber'
import Timeline from './Timeline'
import Button from './Button'
import Customer from './Customer'

const Reasons = withTranslation()(({ order, onClick, loading, t }) => {

  return (
    <div className="d-flex flex-row justify-content-between py-4 border-top">
      <Button onClick={ () => onClick('CUSTOMER') } loading={ loading } icon="user" danger>
        { t('cancel.reason.CUSTOMER') }
      </Button>
      <Button onClick={ () => onClick('SOLD_OUT') } loading={ loading } icon="times" danger>
        { t('cancel.reason.SOLD_OUT') }
      </Button>
      <Button onClick={ () => onClick('RUSH_HOUR') } loading={ loading } icon="fire" danger>
        { t('cancel.reason.RUSH_HOUR') }
      </Button>
      { (order.state === 'accepted' && order.takeaway) && (
      <Button onClick={ () => onClick('NO_SHOW') } loading={ loading } icon="user-times" danger>
        { t('cancel.reason.NO_SHOW') }
      </Button>
      )}
    </div>
  )
})

const LoopeatFormats = withTranslation()(({ t, order, loopeatFormats, updateLoopeatFormats }) => {

  const initialValues = {
    loopeatFormats,
  }

  return (
    <Formik
      initialValues={ initialValues }
      onSubmit={ (values) => {
        updateLoopeatFormats(order, values.loopeatFormats)
      }}
      validateOnBlur={ false }
      validateOnChange={ false }>
      {({
        values,
        submitForm,
      }) => (
      <div>
        { loopeatFormats.map((loopeatFormat, index) => {

          return (
            <div key={ `loopeat-format-${index}` }>
              <h5>{ loopeatFormat.orderItem.name }</h5>
              <table className="table table-condensed">
                <tbody>
                { loopeatFormat.formats.map((format, formatIndex) => (
                  <tr key={ `loopeat-format-format-${formatIndex}` }>
                    <td width="15%">
                      <Field className="form-control input-sm"
                        type="number"
                        value={ values.loopeatFormats[index].formats[formatIndex].quantity }
                        min="0"
                        max={ format.quantity }
                        name={ `loopeatFormats.${index}.formats.${formatIndex}.quantity` } />
                    </td>
                    <td><small>{ format.format_name }</small></td>
                    <td className="text-right">
                      <small className={
                        classNames({
                          'text-success': format.missing_quantity === 0,
                          'text-warning': format.missing_quantity > 0,
                        })
                      }>
                      { format.missing_quantity === 0 && t('ADMIN_DASHBOARD_ORDERS_LOOPEAT_STOCK_OK') }
                      { format.missing_quantity > 0 && t('ADMIN_DASHBOARD_ORDERS_LOOPEAT_STOCK_NOK', { quantity: format.missing_quantity }) }
                      </small>
                    </td>
                  </tr>)
                ) }
                </tbody>
              </table>
            </div>
          )
        }) }
        <div className="text-right">
          <button type="button" className="btn" onClick={ submitForm }>
            { t('CART_DELIVERY_TIME_SUBMIT') }
          </button>
        </div>
      </div>
    )}
    </Formik>
  )
})

class ModalContent extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      mode: 'default'
    }
    this.cancelOrder = this.cancelOrder.bind(this)
  }

  cancelOrder(reason) {
    const { order } = this.props

    this.props.cancelOrder(order, reason)
  }

  delayOrder() {
    const { order } = this.props

    this.props.delayOrder(order)
  }

  refuseOrder(reason) {
    const { order } = this.props

    this.props.refuseOrder(order, reason)
  }

  acceptOrder() {
    const { order } = this.props

    this.props.acceptOrder(order)
  }

  fulfillOrder() {
    const { order } = this.props

    this.props.fulfillOrder(order)
  }

  restoreOrder() {
    const { order } = this.props

    this.props.restoreOrder(order)
  }

  renderButtons() {

    const { loading, order } = this.props
    const { mode } = this.state

    if (mode === 'cancel') {

      return (
        <div>
          <Reasons
            order={ order }
            loading={ loading }
            onClick={ reason => this.cancelOrder(reason) } />
          <div className="text-center text-danger">
            <span>{ this.props.t('ADMIN_DASHBOARD_ORDERS_CANCEL_REASON') }</span>
          </div>
        </div>
      )
    }

    if (mode === 'refuse') {

      return (
        <div>
          <Reasons
            order={ order }
            loading={ loading }
            onClick={ reason => this.refuseOrder(reason) } />
          <div className="text-center text-danger">
            <span>{ this.props.t('ADMIN_DASHBOARD_ORDERS_REFUSE_REASON') }</span>
          </div>
        </div>
      )
    }

    if (order.state === 'cancelled') {

      return (
        <div className="d-flex flex-row justify-content-end py-4 border-top">
          <Button onClick={ this.restoreOrder.bind(this) } loading={ loading } icon="undo" success>
            { this.props.t('ADMIN_DASHBOARD_ORDERS_RESTORE') }
          </Button>
        </div>
      )
    }

    if (order.state === 'new') {

      return (
        <div className="d-flex flex-row justify-content-between py-4 border-top">
          <Button onClick={ () => this.setState({ mode: 'refuse' }) } loading={ loading } icon="ban" danger>
            { this.props.t('ADMIN_DASHBOARD_ORDERS_REFUSE') }
          </Button>
          <Button onClick={ this.acceptOrder.bind(this) } loading={ loading } icon="check" primary>
            { this.props.t('ADMIN_DASHBOARD_ORDERS_ACCEPT') }
          </Button>
        </div>
      )
    }

    if (order.state === 'accepted') {

      return (
        <div className="d-flex flex-row justify-content-between py-4 border-top">
          <Button onClick={ () => this.setState({ mode: 'cancel' }) } loading={ loading } icon="ban" danger>
            { this.props.t('ADMIN_DASHBOARD_ORDERS_CANCEL') }
          </Button>
          { !order.takeaway && (
          <Button onClick={ this.delayOrder.bind(this) } loading={ loading } icon="clock-o" primary>
            { this.props.t('ADMIN_DASHBOARD_ORDERS_DELAY') }
          </Button>
          )}
          { order.takeaway && (
          <Button onClick={ this.fulfillOrder.bind(this) } loading={ loading } icon="check" success>
            { this.props.t('ADMIN_DASHBOARD_ORDERS_FULFILL') }
          </Button>
          )}
        </div>
      )
    }
  }

  renderNotes() {

    const { order } = this.props

    return (
      <div>
        <h5>
          <i className="fa fa-user"></i>  { this.props.t('ADMIN_DASHBOARD_ORDERS_NOTES') }
        </h5>
        <div className="speech-bubble">
          <i className="fa fa-quote-left"></i>  { order.notes }
        </div>
      </div>
    )
  }

  renderPhoneNumber(phoneNumberAsText) {

    const phoneNumber =
      parsePhoneNumberFromString(phoneNumberAsText, this.props.countryCode)

    return (
      <span>
        <span><i className="fa fa-phone"></i></span>
        <span> </span>
        <span><small>{ phoneNumber ? phoneNumber.formatNational() : phoneNumberAsText }</small></span>
      </span>
    )
  }

  renderCustomerDetails(customer) {

    const items = []

    if (customer.givenName && customer.familyName) {
      items.push({
        text: `${customer.givenName} ${customer.familyName}`
      })
    }

    if (customer.telephone) {
      items.push({
        component: this.renderPhoneNumber(customer.telephone)
      })
    }

    if (customer.email) {
      items.push({
        icon: 'envelope-o',
        component: (
          <a href={ `mailto:${customer.email}` }>
            <small>{ customer.email }</small>
          </a>
        )
      })
    }

    return (
      <ul className="list-unstyled">
        { items.map((item, key) => {

          return (
            <li key={ key }>
              { item.icon && (
                <span>
                  <span><i className={ `fa fa-${item.icon}` }></i></span>
                  <span> </span>
                </span>
              ) }
              { item.text && ( <span><small>{ item.text }</small></span> ) }
              { item.component && item.component }
            </li>
          )
        }) }
      </ul>
    )
  }

  renderLoopeatSection() {

    const { order } = this.props

    return (
      <div>
        <div className="text-right mb-3">
          <button type="button" className="btn btn-md"
            onClick={ () => this.props.toggleReusablePackagings(this.props.order) }>Modifier les emballages</button>
        </div>
        { this.props.isLoopeatSectionOpen && (
        <div>
          <LoopeatFormats
            order={ order }
            loopeatFormats={ this.props.loopeatFormats }
            updateLoopeatFormats={ this.props.updateLoopeatFormats } />
        </div>
        ) }
      </div>
    )
  }

  render() {

    const { order, itemsGroups, restaurant, errorMessage } = this.props

    return (
      <div className="panel panel-default">
        <div className="panel-heading">
          <OrderNumber order={ order } />
          <a className="pull-right" onClick={ () => this.props.setCurrentOrder(null) }>
            <i className="fa fa-close"></i>
          </a>
        </div>
        <div className="panel-body">
          <div className="row">
            <div className="col-xs-6">
              <h5>
                <Customer customer={ order.customer } />
              </h5>
            </div>
            <div className="col-xs-6">
              <div className="text-right">
                { this.renderCustomerDetails(order.customer) }
              </div>
            </div>
          </div>
          <div>
            <h4 className="text-center">
              <i className="fa fa-cutlery"></i>  { order.vendor.name }
            </h4>
            { (order.restaurant && order.restaurant.telephone) && (
              <div className="text-center text-muted">
                { this.renderPhoneNumber(order.restaurant.telephone) }
              </div>
            ) }
          </div>
          <h5>{ this.props.t('ADMIN_DASHBOARD_ORDERS_DISHES') }</h5>
          <OrderItems itemsGroups={ itemsGroups } restaurant={ restaurant } />
          <OrderTotal order={ order } />
          { order.notes && this.renderNotes() }
          { (order.restaurant.loopeatEnabled && order.reusablePackagingEnabled) && this.renderLoopeatSection() }
          <h5>{ this.props.t('ADMIN_DASHBOARD_ORDERS_TIMELINE') }</h5>
          <Timeline order={ order } />
          { errorMessage ? <div className="alert alert-danger">{ errorMessage }</div> : null }
          { this.renderButtons() }
        </div>
      </div>
    )
  }
}

function mapStateToProps(state) {

  return {
    countryCode: (getCountry() || 'fr').toUpperCase(),
    loading: state.isFetching,
    itemsGroups: selectItemsGroups(state),
    restaurant: state.restaurant,
    isLoopeatSectionOpen: state.isLoopeatSectionOpen,
    reusablePackagings: state.reusablePackagings,
    loopeatFormats: state.loopeatFormats,
    errorMessage: state.errorMessage,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    setCurrentOrder: order => dispatch(setCurrentOrder(order)),
    acceptOrder: order => dispatch(acceptOrder(order)),
    refuseOrder: (order, reason) => dispatch(refuseOrder(order, reason)),
    delayOrder: order => dispatch(delayOrder(order)),
    cancelOrder: (order, reason) => dispatch(cancelOrder(order, reason)),
    fulfillOrder: order => dispatch(fulfillOrder(order)),
    restoreOrder: order => dispatch(restoreOrder(order)),
    toggleReusablePackagings: order => dispatch(toggleReusablePackagings(order)),
    updateLoopeatFormats: (order, loopeatFormats) => dispatch(updateLoopeatFormats(order, loopeatFormats)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(ModalContent))
