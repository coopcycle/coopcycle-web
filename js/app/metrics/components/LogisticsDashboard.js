import React from 'react'
import { connect } from 'react-redux'

import AverageDistance from './AverageDistance'
import StoreCumulativeCount from './StoreCumulativeCount'
import NumberOfTasksByStatus from './NumberOfTasksByStatus'
import PercentageOfTasksByStatus from './PercentageOfTasksByStatus'
import Navbar from './Navbar'
import ChartPanel from './ChartPanel'

const Dashboard = ({ cubejsApi, dateRange }) => {

  return (
    <div>
      <Navbar />
      <div className="metrics-grid">
        <ChartPanel title="Average distance">
          <AverageDistance cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of stores">
          <StoreCumulativeCount cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number Of Tasks By Status">
          <NumberOfTasksByStatus cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Percentage Of Tasks By Status">
          <PercentageOfTasksByStatus cubejsApi={ cubejsApi } dateRange={ dateRange } />
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
