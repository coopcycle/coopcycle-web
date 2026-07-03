import React from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'

import ZeroWasteOrderCount from './ZeroWasteOrderCount'
import ChartPanel from './ChartPanel'

const Dashboard = ({ dateRange }) => {
  const { t } = useTranslation()

  return (
    <div>
      <div className="metrics-grid">
        <ChartPanel title={t('METRICS.ZERO_WASTE_ORDERS')}>
          <ZeroWasteOrderCount dateRange={ dateRange } />
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
