import React from 'react'
import { connect } from 'react-redux'

import AverageDistance from './AverageDistance'
import NumberOfTasks from './NumberOfTasks'
import Navbar from './Navbar'

const Dashboard = ({ cubejsApi, dateRange }) => {

  return (
    <div>
      <Navbar />
      <div style={{ minHeight: '240px' }}>
        <AverageDistance cubejsApi={ cubejsApi } dateRange={ dateRange } />
      </div>
      <div style={{ minHeight: '240px' }}>
        <NumberOfTasks cubejsApi={ cubejsApi } dateRange={ dateRange } />
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
