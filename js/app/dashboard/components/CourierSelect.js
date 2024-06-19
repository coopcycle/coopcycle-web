import React, { Component } from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Select, { components } from 'react-select'
import _ from 'lodash'

import Avatar from '../../components/Avatar'
import { selectCouriersWithExclude } from '../redux/selectors'

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
      <div data-cypress-select-username={ props.data.username }>
        <Avatar username={ props.data.username } />  { children }
      </div>
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

    const options = this.props.couriers.map(courierAsOption)
    const {isMulti } = this.props

    return (
      <Select
        defaultValue={ lookupCourier(options, this.props.username) }
        options={ options }
        onChange={ this.props.onChange }
        placeholder={ isMulti ? this.props.t('ADMIN_DASHBOARD_COURIERS_SELECT_PLACEHOLDER') : this.props.t('ADMIN_DASHBOARD_COURIER_SELECT_PLACEHOLDER') }
        components={{ Option, SingleValue }}
        menuPlacement={ Object.prototype.hasOwnProperty.call(this.props, 'menuPlacement') ? this.props.menuPlacement : 'auto' }
        isDisabled={ Object.prototype.hasOwnProperty.call(this.props, 'isDisabled') ? this.props.isDisabled : false }
        maxMenuHeight={ 160 }
        isClearable={ Object.prototype.hasOwnProperty.call(this.props, 'isClearable') ? this.props.isClearable : false }
        // https://github.com/coopcycle/coopcycle-web/issues/774
        // https://github.com/JedWatson/react-select/issues/3030
        menuPortalTarget={ document.body }
        styles={{
          menuPortal: base => ({ ...base, zIndex: 9 })
        }}
        isMulti={isMulti}
        closeMenuOnSelect={!isMulti}
      />
    )
  }
}

function mapStateToProps(state, ownProps) {

  return {
    couriers: selectCouriersWithExclude(state, ownProps.exclude),
  }
}

export default connect(mapStateToProps)(withTranslation()(CourierSelect))
