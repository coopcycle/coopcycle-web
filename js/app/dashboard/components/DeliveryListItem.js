import React from 'react'

export default class extends React.Component {
  render() {

    const { delivery } = this.props

    return (
      <div key={ delivery['@id'] } href="#" className="list-group-item" data-delivery-id={ delivery['@id'] }>
        <span>{ delivery.originAddress.streetAddress }</span>
        <br />
        <span>{ delivery.deliveryAddress.streetAddress }</span>
      </div>
    )
  }
}
