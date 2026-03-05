import React, { useState } from 'react'
import { connect } from 'react-redux'
import { InputNumber, Form } from 'antd';
import _ from 'lodash'
import { useTranslation } from 'react-i18next'

import ProfitabilityHeatmap from './ProfitabilityHeatmap'
import ProfitabilityBars from './ProfitabilityBars'
import ChartPanel from './ChartPanel'

const Dashboard = ({ dateRange }) => {

  const [ fixedCosts, setFixedCosts ] = useState(0)
  const { t } = useTranslation()

  const setFixedCostsDebounced = _.debounce(setFixedCosts, 300)

  return (
    <div>
      <Form.Item label={ t('MONTHLY_FIXED_COSTS') }>
        <InputNumber defaultValue={ fixedCosts } onChange={ setFixedCostsDebounced } />
      </Form.Item>
      <div className="metrics-grid">
        <ChartPanel title={t('METRICS.INCOME_PER_DAY_AND_HOUR')} className="d-block">
          <ProfitabilityHeatmap dateRange={ dateRange } fixedCosts={ fixedCosts } />
        </ChartPanel>
        <ChartPanel title={t('METRICS.INCOME_PER_WEEK')}>
          <ProfitabilityBars dateRange={ dateRange } fixedCosts={ fixedCosts } />
        </ChartPanel>
      </div>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    dateRange: state.dateRange,
  }
}

export default connect(mapStateToProps)(Dashboard)
