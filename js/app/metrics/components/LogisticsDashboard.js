import React from 'react'
import { connect } from 'react-redux'

import ChartPanel from './ChartPanel'
import AverageDistance from './AverageDistance'
import NumberOfTasks from './NumberOfTasks'
import StoreCumulativeCount from './StoreCumulativeCount'
import DistributionOfTasksByTiming from './DistributionOfTasksByTiming'
import AverageTiming from './AverageTiming'
import DistributionOfTasksByPercentage from './DistributionOfTasksByPercentage'
import TasksDoneTiming from './TasksDoneTiming'

const Dashboard = ({ cubejsApi, dateRange, tasksMetricsEnabled }) => {

  return (
    <div>
      <div className="metrics-grid">
        <ChartPanel title="Average distance">
          <AverageDistance cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of stores">
          <StoreCumulativeCount cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of tasks">
          <NumberOfTasks cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        {tasksMetricsEnabled && (<div/>)}
        {tasksMetricsEnabled && (
          <ChartPanel title="Tasks done on time, too early or too late">
            <TasksDoneTiming
              cubejsApi={ cubejsApi }
              dateRange={ dateRange } />
          </ChartPanel>
        )}
        {tasksMetricsEnabled && (
          <ChartPanel title="Average number of minutes Tasks are done too early/late">
            <AverageTiming
              cubejsApi={ cubejsApi }
              dateRange={ dateRange } />
          </ChartPanel>
        )}
        {tasksMetricsEnabled && (
          <ChartPanel title="Number Of PICKUPs done X minutes earlier/later than planned">
            <DistributionOfTasksByTiming
              cubejsApi={ cubejsApi }
              dateRange={ dateRange }
              taskType="PICKUP"/>
          </ChartPanel>
        )}
        {tasksMetricsEnabled && (
          <ChartPanel title="Number Of DROPOFFs done X minutes earlier/later than planned">
            <DistributionOfTasksByTiming
              cubejsApi={ cubejsApi }
              dateRange={ dateRange }
              taskType="DROPOFF"/>
          </ChartPanel>
        )}
        {tasksMetricsEnabled && (
          <ChartPanel title="Number Of PICKUPs done % earlier/later than planned">
            <DistributionOfTasksByPercentage
              cubejsApi={ cubejsApi }
              dateRange={ dateRange }
              taskType="PICKUP"/>
          </ChartPanel>
        )}
        {tasksMetricsEnabled && (
          <ChartPanel title="Number Of DROPOFFs done % earlier/later than planned">
            <DistributionOfTasksByPercentage
              cubejsApi={ cubejsApi }
              dateRange={ dateRange }
              taskType="DROPOFF"/>
          </ChartPanel>
        )}
      </div>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    dateRange: state.dateRange,
    tasksMetricsEnabled: state.uiTasksMetricsEnabled,
  }
}

export default connect(mapStateToProps)(Dashboard)
