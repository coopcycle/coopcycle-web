import React from 'react'
import { connect } from 'react-redux'
import { DatePicker } from 'antd'
import dayjs from 'dayjs'
import weekday from 'dayjs/plugin/weekday'
import localeData from 'dayjs/plugin/localeData'

dayjs.extend(weekday)
dayjs.extend(localeData)

import { useTranslation } from 'react-i18next'

import { changeDateRange, changeView } from '../redux/actions'

const { RangePicker } = DatePicker

const Navbar = ({ dateRange, changeDateRange, changeView, zeroWaste }) => {
  const { t } = useTranslation()

  const presets = [
    { label: t('METRICS.PRESET_THIS_WEEK'),     value: [ dayjs().startOf('week'),                          dayjs().endOf('week') ] },
    { label: t('METRICS.PRESET_LAST_WEEK'),     value: [ dayjs().subtract(1, 'week').startOf('week'),      dayjs().subtract(1, 'week').endOf('week') ] },
    { label: t('METRICS.PRESET_THIS_MONTH'),    value: [ dayjs().startOf('month'),                         dayjs().endOf('month') ] },
    { label: t('METRICS.PRESET_LAST_MONTH'),    value: [ dayjs().subtract(1, 'month').startOf('month'),    dayjs().subtract(1, 'month').endOf('month') ] },
    { label: t('METRICS.PRESET_LAST_3_MONTHS'), value: [ dayjs().subtract(3, 'month').startOf('month'),    dayjs() ] },
    { label: t('METRICS.PRESET_LAST_6_MONTHS'), value: [ dayjs().subtract(6, 'month').startOf('month'),    dayjs() ] },
  ]

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
        presets={presets}
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
