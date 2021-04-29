import React from 'react'
import { connect } from 'react-redux'
import { Select } from 'antd'

import { changeDateRange, changeView } from '../redux/actions'

const { Option } = Select

const Navbar = ({ dateRange, changeDateRange, changeView }) => {

  return (
    <div className="d-flex align-items-center justify-content-between border-bottom py-4 mb-4">
      <div>
        <a href="#" onClick={ e => {
          e.preventDefault()
          changeView('marketplace')
        }}>Marketplace</a>
        <span className="mx-2">|</span>
        <a href="#" onClick={ e => {
          e.preventDefault()
          changeView('logistics')
        }}>Logistics</a>
      </div>
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
    changeDateRange: dateRange => dispatch(changeDateRange(dateRange)),
    changeView: view => dispatch(changeView(view)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(Navbar)
