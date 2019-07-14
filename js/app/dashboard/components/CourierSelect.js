import React, { Component } from 'react'
import { connect } from 'react-redux'
import Select, { components } from 'react-select'

const courierAsOption = courier => ({
  ...courier,
  value: courier.username,
  label: courier.username
})

const lookupCourier = (couriers, username) => {
  return _.find(couriers, courier => courier.username === username)
}

const Option = ({ children, ...props }) => {
  const iconUrl =
    window.Routing.generate('user_avatar', { username: props.data.username })

  return (
    <components.Option { ...props }>
      <img src={ iconUrl } width="20" height="20" />  { children }
    </components.Option>
  )
}

const SingleValue = ({ children, ...props }) => {
  const iconUrl =
    window.Routing.generate('user_avatar', { username: props.data.username })

  return (
    <components.SingleValue { ...props }>
      <img src={ iconUrl } width="20" height="20" />  { children }
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
        components={{ Option, SingleValue }}
        menuPlacement={ this.props.hasOwnProperty('menuPlacement') ? this.props.menuPlacement : 'auto' }
        isDisabled={ this.props.hasOwnProperty('isDisabled') ? this.props.isDisabled : false }
        maxMenuHeight={ 160 } />
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

export default connect(mapStateToProps)(CourierSelect)
