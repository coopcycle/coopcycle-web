import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Select, { components } from 'react-select'
import _ from 'lodash'

import Avatar from '../../components/Avatar'

import { selectTaskLists } from '../../coopcycle-frontend-js/logistics/redux'

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
        menuPlacement={ Object.prototype.hasOwnProperty.call(this.props, 'menuPlacement') ? this.props.menuPlacement : 'auto' }
        isDisabled={ Object.prototype.hasOwnProperty.call(this.props, 'isDisabled') ? this.props.isDisabled : false }
        maxMenuHeight={ 160 }
        isClearable={ Object.prototype.hasOwnProperty.call(this.props, 'isClearable') ? this.props.isClearable : false } />
    )
  }
}

function mapStateToProps(state, ownProps) {

  let couriers = state.couriersList

  if (Object.prototype.hasOwnProperty.call(ownProps, 'exclude') && ownProps.exclude) {
    const usernames = _.map(selectTaskLists(state), taskList => taskList.username)
    couriers = _.filter(couriers, courier => !_.includes(usernames, courier.username))
  }

  return {
    couriers: _.map(couriers, courierAsOption),
  }
}

export default connect(mapStateToProps)(withTranslation()(CourierSelect))
