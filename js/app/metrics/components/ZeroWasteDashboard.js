import React from 'react'
import { connect } from 'react-redux'

import ZeroWasteOrderCount from './ZeroWasteOrderCount'
import ChartPanel from './ChartPanel'

const Dashboard = ({ cubejsApi, dateRange }) => {

  return (
    <div>
      <div className="metrics-grid">
        <ChartPanel title="Zero waste orders">
          <ZeroWasteOrderCount cubejsApi={ cubejsApi } dateRange={ dateRange } />
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
