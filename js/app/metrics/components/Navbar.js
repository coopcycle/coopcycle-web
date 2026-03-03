import React from 'react'
import { connect } from 'react-redux'
import { DatePicker } from 'antd'
import dayjs from 'dayjs'
import weekday from 'dayjs/plugin/weekday'
import localeData from 'dayjs/plugin/localeData'

dayjs.extend(weekday)
dayjs.extend(localeData)

import { changeDateRange, changeView } from '../redux/actions'

const { RangePicker } = DatePicker

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
      <RangePicker
        allowClear={false}
        value={[ dayjs(dateRange[0]), dayjs(dateRange[1]) ]}
        onChange={(_, dateStrings) => changeDateRange(dateStrings)}
      />
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
