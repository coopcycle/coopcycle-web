import React from 'react'
import { connect } from 'react-redux'
import { DatePicker, Tabs } from 'antd'
import dayjs from 'dayjs'
import weekday from 'dayjs/plugin/weekday'
import localeData from 'dayjs/plugin/localeData'

dayjs.extend(weekday)
dayjs.extend(localeData)

import { useTranslation } from 'react-i18next'

import { changeDateRange, changeView } from '../redux/actions'

const { RangePicker } = DatePicker

const Navbar = ({ view, dateRange, changeDateRange, changeView, zeroWaste }) => {
  const { t } = useTranslation()

  const presets = [
    { label: t('METRICS.PRESET_THIS_WEEK'),     value: [ dayjs().startOf('week'),                          dayjs().endOf('week') ] },
    { label: t('METRICS.PRESET_LAST_WEEK'),     value: [ dayjs().subtract(1, 'week').startOf('week'),      dayjs().subtract(1, 'week').endOf('week') ] },
    { label: t('METRICS.PRESET_THIS_MONTH'),    value: [ dayjs().startOf('month'),                         dayjs().endOf('month') ] },
    { label: t('METRICS.PRESET_LAST_MONTH'),    value: [ dayjs().subtract(1, 'month').startOf('month'),    dayjs().subtract(1, 'month').endOf('month') ] },
    { label: t('METRICS.PRESET_LAST_3_MONTHS'), value: [ dayjs().subtract(3, 'month').startOf('month'),    dayjs() ] },
    { label: t('METRICS.PRESET_LAST_6_MONTHS'), value: [ dayjs().subtract(6, 'month').startOf('month'),    dayjs() ] },
  ]

  const items = [
    { key: 'marketplace',   label: t('METRICS.NAV_MARKETPLACE') },
    { key: 'logistics',     label: t('METRICS.NAV_LOGISTICS') },
    { key: 'profitability', label: t('METRICS.NAV_PROFITABILITY') },
    ...(zeroWaste ? [{ key: 'zerowaste', label: t('METRICS.NAV_ZERO_WASTE') }] : []),
  ]

  return (
    <Tabs
      activeKey={view}
      items={items}
      onChange={changeView}
      tabBarExtraContent={{
        right: (
          <RangePicker
            allowClear={false}
            presets={presets}
            value={[ dayjs(dateRange[0]), dayjs(dateRange[1]) ]}
            onChange={(_, dateStrings) => changeDateRange(dateStrings)}
          />
        )
      }}
    />
  )
}

function mapStateToProps(state) {

  return {
    view: state.view,
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
