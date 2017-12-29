import React from 'react'
import { findDOMNode } from 'react-dom'
import DeliveryListItem from './DeliveryListItem'

export default class extends React.Component {
  constructor(props) {
    super(props)
    this.state = {
      deliveries: props.deliveries || [],
    }
  }

  componentDidMount() {
    this.props.onLoad(findDOMNode(this))
  }
  add(delivery) {

    let { deliveries } = this.state

    deliveries = deliveries.slice()
    deliveries.push(delivery)

    this.setState({ deliveries })
  }
  remove(delivery) {

    let { deliveries } = this.state

    deliveries = deliveries.slice()
    deliveries = _.filter(deliveries, item => item['@id'] !== delivery['@id'])

    this.setState({ deliveries })
  }
  render() {
    return (
      <div className="list-group">
        { this.state.deliveries.map(delivery => {
          return (
            <DeliveryListItem key={ delivery['@id'] } delivery={ delivery } />
          )
        })}
      </div>
    )
  }
}
