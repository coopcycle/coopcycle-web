import React, { useState } from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'

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

const Dashboard = ({ dateRange, allTags, tasksMetricsEnabled }) => {
  const [ selectedTags, setSelectedTags ] = useState([])
  const { t } = useTranslation()

  return (
    <div>
      <div className="metrics-grid">
        <ChartPanel title={t('METRICS.NUMBER_OF_STORES')}>
          <StoreCumulativeCount dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title={t('METRICS.AVERAGE_DISTANCE')}>
          <AverageDistance dateRange={ dateRange } />
        </ChartPanel>
      </div>
      <div style={selectedTags.length > 0 ? hasFilterContainerStyle : baseContainerStyle}>
        <TagsSelect tags={ allTags }
                    defaultValue={ selectedTags }
                    onChange={ tags => setSelectedTags(tags) } />
        <div className="metrics-grid" style={gridWithFilterStyle}>
          <ChartPanel title={t('METRICS.NUMBER_OF_TASKS')}>
            <NumberOfTasks dateRange={ dateRange } tags={ selectedTags } />
          </ChartPanel>
          {tasksMetricsEnabled && (
            <>
              <ChartPanel title={t('METRICS.TASKS_TIMING')} featurePreview={true}>
                <TasksDoneTiming
                  dateRange={ dateRange }
                  tags={ selectedTags } />
              </ChartPanel>
              <div/>
              <ChartPanel title={t('METRICS.AVERAGE_TASK_TIMING_MINUTES')} featurePreview={true}>
                <AverageTiming
                  dateRange={ dateRange }
                  tags={ selectedTags } />
              </ChartPanel>
            </>
          )}
        </div>
      </div>
      <div className="metrics-grid">
        {tasksMetricsEnabled && (
          <ChartPanel title={t('METRICS.PICKUPS_TIMING_DISTRIBUTION')} featurePreview={true}>
            <DistributionOfTasksByTiming
              dateRange={ dateRange }
              taskType="PICKUP"/>
          </ChartPanel>
        )}
        {tasksMetricsEnabled && (
          <ChartPanel title={t('METRICS.DROPOFFS_TIMING_DISTRIBUTION')} featurePreview={true}>
            <DistributionOfTasksByTiming
              dateRange={ dateRange }
              taskType="DROPOFF"/>
          </ChartPanel>
        )}
        {tasksMetricsEnabled && (
          <ChartPanel title={t('METRICS.PICKUPS_PERCENTAGE_DISTRIBUTION')} featurePreview={true}>
            <DistributionOfTasksByPercentage
              dateRange={ dateRange }
              taskType="PICKUP"/>
          </ChartPanel>
        )}
        {tasksMetricsEnabled && (
          <ChartPanel title={t('METRICS.DROPOFFS_PERCENTAGE_DISTRIBUTION')} featurePreview={true}>
            <DistributionOfTasksByPercentage
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
