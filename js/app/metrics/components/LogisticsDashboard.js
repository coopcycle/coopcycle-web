import React from 'react'
import { connect } from 'react-redux'

import AverageDistance from './AverageDistance'
import NumberOfTasks from './NumberOfTasks'
import StoreCumulativeCount from './StoreCumulativeCount'
import Navbar from './Navbar'
import ChartPanel from './ChartPanel'
import PercentageOfTasksByTiming from './PercentageOfTasksByTiming'
import NumberOfTasksByTiming from './NumberOfTasksByTiming'
import DistributionOfTasksByTiming from './DistributionOfTasksByTiming'
import AverageTiming from './AverageTiming'

const Dashboard = ({ cubejsApi, dateRange }) => {

  return (
    <div>
      <Navbar />
      <div className="metrics-grid">
        <ChartPanel title="Average distance">
          <AverageDistance cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of tasks">
          <NumberOfTasks cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of stores">
          <StoreCumulativeCount cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <div>
        </div>
        <ChartPanel title="Percentage Of Tasks NOT on time">
          <PercentageOfTasksByTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number Of Tasks NOT on time">
          <NumberOfTasksByTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Average delay">
          <AverageTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Distribution Of Pickups By Timing">
          <DistributionOfTasksByTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange }
            taskType="PICKUP"/>
        </ChartPanel>
        <ChartPanel title="Distribution Of Dropoffs By Timing">
          <DistributionOfTasksByTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange }
            taskType="DROPOFF"/>
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
