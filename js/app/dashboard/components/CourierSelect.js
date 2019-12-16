import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Select, { components } from 'react-select'
import _ from 'lodash'

import Avatar from './Avatar'

const courierAsOption = courier => ({
  ...courier,
  value: courier.username,
  label: courier.username
})

const lookupCourier = (couriers, username) => {
  return _.find(couriers, courier => courier.username === username)
}

const Option = ({ children, ...props }) => {

  return (
    <components.Option { ...props }>
      <Avatar username={ props.data.username } />  { children }
    </components.Option>
  )
}

const SingleValue = ({ children, ...props }) => {

  return (
    <components.SingleValue { ...props }>
      <Avatar username={ props.data.username } />  { children }
    </components.SingleValue>
  )
}

class CourierSelect extends Component {

  render() {
    return (
      <Select
        defaultValue={ lookupCourier(this.props.couriers, this.props.username) }
        options={ this.props.couriers }
        onChange={ this.props.onChange }
        placeholder={ this.props.t('ADMIN_DASHBOARD_COURIER_SELECT_PLACEHOLDER') }
        components={{ Option, SingleValue }}
        menuPlacement={ this.props.hasOwnProperty('menuPlacement') ? this.props.menuPlacement : 'auto' }
        isDisabled={ this.props.hasOwnProperty('isDisabled') ? this.props.isDisabled : false }
        maxMenuHeight={ 160 }
        isClearable={ this.props.hasOwnProperty('isClearable') ? this.props.isClearable : false } />
    )
  }
}

function mapStateToProps(state, ownProps) {

  let couriers = state.couriersList

  if (ownProps.hasOwnProperty('exclude') && ownProps.exclude) {
    const usernames = _.map(state.taskLists, taskList => taskList.username)
    couriers = _.filter(couriers, courier => !_.includes(usernames, courier.username))
  }

  return {
    couriers: _.map(couriers, courierAsOption),
  }
}

export default connect(mapStateToProps)(withTranslation()(CourierSelect))
