import React, { useState } from 'react'
import { connect } from 'react-redux'
import { InputNumber, Form } from 'antd';
import _ from 'lodash'
import { useTranslation } from 'react-i18next'

import ProfitabilityHeatmap from './ProfitabilityHeatmap'
import ProfitabilityBars from './ProfitabilityBars'
import ChartPanel from './ChartPanel'

const Dashboard = ({ cubejsApi, dateRange }) => {

  const [ fixedCosts, setFixedCosts ] = useState(0)
  const { t } = useTranslation()

  const setFixedCostsDebounced = _.debounce(setFixedCosts, 300)

  return (
    <div>
      <Form.Item label={ t('MONTHLY_FIXED_COSTS') }>
        <InputNumber defaultValue={ fixedCosts } onChange={ setFixedCostsDebounced } />
      </Form.Item>
      <div className="metrics-grid">
        <ChartPanel title="Income per day of week and hour range" className="d-block">
          <ProfitabilityHeatmap cubejsApi={ cubejsApi } dateRange={ dateRange } fixedCosts={ fixedCosts } />
        </ChartPanel>
        <ChartPanel title="Income per week">
          <ProfitabilityBars cubejsApi={ cubejsApi } dateRange={ dateRange } fixedCosts={ fixedCosts } />
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
