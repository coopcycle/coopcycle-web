import React from 'react'
import classNames from 'classnames'

import OrderCard from './OrderCard'

export default ({ title, orders, active, context }) => {

  return (
    <div className={ classNames({
      'FoodtechDashboard__Column': true,
      'FoodtechDashboard__Column--active': active }) }>
      <div className={ `panel panel-${context}` }>
        <div className="panel-heading">
          <span>{ title }</span>
          <span className="pull-right">{ `(${orders.length})` }</span>
        </div>
        <div className="panel-body">
          { orders.map((order, key) => (
            <OrderCard key={ key } order={ order } />
          )) }
        </div>
      </div>
    </div>
  )
}
