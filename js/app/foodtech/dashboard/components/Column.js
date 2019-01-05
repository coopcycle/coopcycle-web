import React from 'react'

import OrderCard from './OrderCard'

class Column extends React.Component {

  render() {

    return (
      <div className="panel panel-default FoodtechDashboard__Column">
        <div className="panel-heading text-center">{ `${this.props.title} (${this.props.orders.length})` }</div>
        <div className="panel-body">
          { this.props.orders.map((order, key) => (
            <OrderCard key={ key } order={ order } />
          )) }
        </div>
      </div>
    )
  }

}

export default Column
