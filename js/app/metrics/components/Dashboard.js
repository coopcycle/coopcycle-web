import React from 'react'
import { connect } from 'react-redux'

import LogisticsDashboard from './LogisticsDashboard'
import MarketplaceDashboard from './MarketplaceDashboard'

const Dashboard = ({ cubejsApi, view }) => {

  return (
    <React.Fragment>
      { view === 'marketplace' && <MarketplaceDashboard cubejsApi={ cubejsApi } /> }
      { view === 'logistics'   && <LogisticsDashboard cubejsApi={ cubejsApi } /> }
    </React.Fragment>
  )
}

function mapStateToProps(state) {

  return {
    view: state.view,
  }
}

export default connect(mapStateToProps)(Dashboard)
