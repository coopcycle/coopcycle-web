import React from 'react'
import { findDOMNode } from 'react-dom'
import UserPanel from './UserPanel'
import moment from 'moment'
import dragula from 'react-dragula'
import _ from 'lodash'

export default class extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      users: props.users || []
    }
  }

  add(user) {
    let { users } = this.state
    users = users.slice()
    users.push(user)
    this.setState({ users })
  }

  render() {

    const { deliveries, map, planning } = this.props

    return (
      <div id="accordion">
      { this.state.users.map(user => {

        let userDeliveries = []
        let markersOrder = []
        if (planning.hasOwnProperty(user.username)) {

          const userPlanning = planning[user.username]
          const keys = _.keys(_.groupBy(userPlanning, item => item.delivery))

          userDeliveries = _.map(keys, key => _.find(deliveries, delivery => delivery['@id'] === key))
          markersOrder = userPlanning.map(item => {
            const isPickup = _.find(userDeliveries, delivery => delivery.originAddress['@id'] === item.address)
            return item.delivery + (isPickup ? '#pickup' : '#dropoff')
          })
        }

        return (
          <UserPanel
            key={ user.username }
            user={ user }
            deliveries={ userDeliveries }
            markersOrder={ markersOrder }
            map={ map }
            onShow={() => {
              map.showLayers(user.username)
              map.zoom(user.username)
            }}
            onHide={ () => map.hideLayers(user.username) }
            onRemove={ delivery => this.props.onRemove(delivery) }
            onLoad={ (component, element) => this.props.onLoad(component, element.querySelector('.panel .list-group')) }
            save={markers => {
              const data = markers.map((marker, index) => {
                return {
                  delivery: marker.delivery['@id'],
                  address: marker.address['@id'],
                  position: index
                }
              })
              return $.ajax({
                url: window.AppData.Dashboard.planningURL.replace('__USERNAME__', user.username),
                type: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
              })
            }} />
          )
      })}
      </div>
    )
  }
}
