import React from 'react'
import { connect } from 'react-redux'
import { Select } from 'antd'
import _ from 'lodash'

import { changeDateRange, changeView } from '../redux/actions'
import { dateRanges } from '../utils'

const { Option } = Select

const Navbar = ({ dateRange, changeDateRange, changeView, zeroWaste }) => {

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
        <span className="mx-2">|</span>
        <a href="#" onClick={ e => {
          e.preventDefault()
          changeView('profitability')
        }}>Profitability</a>
        { zeroWaste && (
        <React.Fragment>
          <span className="mx-2">|</span>
          <a href="#" onClick={ e => {
            e.preventDefault()
            changeView('zerowaste')
          }}>Zero waste</a>
        </React.Fragment>
        ) }
      </div>
      <Select
        defaultValue={ dateRange }
        onChange={ changeDateRange }>
        { _.map(dateRanges, (label, key) =>
          <Option key={ key } value={ key }>{ label }</Option>
        )}
      </Select>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    dateRange: state.dateRange,
    zeroWaste: state.zeroWaste,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    changeDateRange: dateRange => dispatch(changeDateRange(dateRange)),
    changeView: view => dispatch(changeView(view)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(Navbar)
