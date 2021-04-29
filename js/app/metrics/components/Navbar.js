import React from 'react'
import { connect } from 'react-redux'
import { Select } from 'antd'

import { changeDateRange } from '../redux/actions'

const { Option } = Select

const Navbar = ({ dateRange, changeDateRange }) => {

  return (
    <div className="d-flex justify-content-end border-bottom py-4 mb-4">
      <Select
        defaultValue={ dateRange }
        onChange={ changeDateRange }>
        <Option value="30d">Last 30 days</Option>
        <Option value="3mo">Last 3 months</Option>
      </Select>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    dateRange: state.dateRange,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    changeDateRange: dateRange => dispatch(changeDateRange(dateRange))
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(Navbar)
