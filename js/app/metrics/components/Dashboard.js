import React from 'react'
import { connect } from 'react-redux'

import LogisticsDashboard from './LogisticsDashboard'
import MarketplaceDashboard from './MarketplaceDashboard'
import ZeroWasteDashboard from './ZeroWasteDashboard'

const Dashboard = ({ cubejsApi, view }) => {

  return (
    <React.Fragment>
      { view === 'marketplace' && <MarketplaceDashboard cubejsApi={ cubejsApi } /> }
      { view === 'logistics'   && <LogisticsDashboard cubejsApi={ cubejsApi } /> }
      { view === 'zerowaste'   && <ZeroWasteDashboard cubejsApi={ cubejsApi } /> }
    </React.Fragment>
  )
}

function mapStateToProps(state) {

  return {
    view: state.view,
  }
}

export default connect(mapStateToProps)(Dashboard)
