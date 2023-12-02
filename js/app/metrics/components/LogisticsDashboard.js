import React, { useState } from 'react'
import { connect } from 'react-redux'

import Navbar from './Navbar'
import ChartPanel from './ChartPanel'
import AverageDistance from './AverageDistance'
import NumberOfTasks from './NumberOfTasks'
import StoreCumulativeCount from './StoreCumulativeCount'
import DistributionOfTasksByTiming from './DistributionOfTasksByTiming'
import AverageTiming from './AverageTiming'
import DistributionOfTasksByPercentage from './DistributionOfTasksByPercentage'
import TasksDoneTiming from './TasksDoneTiming'
import TagsSelect from '../../components/TagsSelect'

const baseContainerStyle = {
  marginTop: 16,
  marginBottom: 16,
  padding: 8,
  borderColor: 'hsl(0, 0%, 80%)',
  borderStyle: 'solid',
  borderWidth: 1,
  borderRadius: 4,
}

const hasFilterContainerStyle = {
  ...baseContainerStyle,
  borderColor: '#2684FF', // default color from react-select
}

const gridWithFilterStyle = {
  paddingTop: 8,
}

const Dashboard = ({ cubejsApi, dateRange, allTags, tasksMetricsEnabled }) => {
  const [ selectedTags, setSelectedTags ] = useState([])

  return (
    <div>
      <Navbar />
      <div className="metrics-grid">
        <ChartPanel title="Number of stores">
          <StoreCumulativeCount cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Average distance">
          <AverageDistance cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
      </div>
      <div style={selectedTags.length > 0 ? hasFilterContainerStyle : baseContainerStyle}>
        <TagsSelect tags={ allTags }
                    defaultValue={ selectedTags }
                    onChange={ tags => setSelectedTags(tags) } />
        <div className="metrics-grid" style={gridWithFilterStyle}>
          <ChartPanel title="Number of tasks">
            <NumberOfTasks cubejsApi={ cubejsApi } dateRange={ dateRange } tags={ selectedTags } />
          </ChartPanel>
          {tasksMetricsEnabled && (
            <>
              <ChartPanel title="Tasks done on time, too early or too late">
                <TasksDoneTiming
                  cubejsApi={ cubejsApi }
                  dateRange={ dateRange }
                  tags={ selectedTags } />
              </ChartPanel>
              <div/>
              <ChartPanel title="Average number of minutes Tasks are done too early/late">
                <AverageTiming
                  cubejsApi={ cubejsApi }
                  dateRange={ dateRange }
                  tags={ selectedTags } />
              </ChartPanel>
            </>
          )}
        </div>
      </div>
      <div className="metrics-grid">
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
    allTags: state.tags,
    tasksMetricsEnabled: state.uiTasksMetricsEnabled,
  }
}

export default connect(mapStateToProps)(Dashboard)
