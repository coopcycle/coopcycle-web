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
import PercentageOfTasksOnTime from './PercentageOfTasksOnTime'
import DistributionOfTasksByPercentage from './DistributionOfTasksByPercentage'

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
        <ChartPanel title="Percentage Of Tasks done on time">
          <PercentageOfTasksOnTime
            cubejsApi={ cubejsApi }
            dateRange={ dateRange } />
        </ChartPanel>
        <div>
        </div>
        <ChartPanel title="Percentage Of Tasks done too early/late">
          <PercentageOfTasksByTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number Of Tasks done too early/late">
          <NumberOfTasksByTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Average number of minutes Tasks are done too early/late">
          <AverageTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange } />
        </ChartPanel>
        <div>
        </div>
        <ChartPanel title="Number Of PICKUPs done X minutes earlier/later than planned">
          <DistributionOfTasksByTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange }
            taskType="PICKUP"/>
        </ChartPanel>
        <ChartPanel title="Number Of DROPOFFs done X minutes earlier/later than planned">
          <DistributionOfTasksByTiming
            cubejsApi={ cubejsApi }
            dateRange={ dateRange }
            taskType="DROPOFF"/>
        </ChartPanel>
        <ChartPanel title="Number Of PICKUPs done % earlier/later than planned">
          <DistributionOfTasksByPercentage
            cubejsApi={ cubejsApi }
            dateRange={ dateRange }
            taskType="PICKUP"/>
        </ChartPanel>
        <ChartPanel title="Number Of DROPOFFs done % earlier/later than planned">
          <DistributionOfTasksByPercentage
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
