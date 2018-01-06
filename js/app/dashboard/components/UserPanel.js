import React from 'react'
import { findDOMNode } from 'react-dom'
import Dragula from 'react-dragula';
import DeliveryListItem from './DeliveryListItem'
import moment from 'moment'
import dragula from 'react-dragula'
import _ from 'lodash'

export default class extends React.Component {

  constructor(props) {
    super(props)

    if (props.deliveries) {
      _.each(props.deliveries, delivery => props.map.addMarkers(props.user.username, delivery))
    }

    this.state = {
      drop: true,
      deliveries: props.deliveries || [],
      duration: 0,
      distance: 0,
      markersOrder: props.markersOrder || [],
      loading: false,
      collapsed: true,
    }
  }

  componentDidMount() {

    this.props.onLoad(this, findDOMNode(this))

    const { user } = this.props
    $('#collapse-' + user.username).on('shown.bs.collapse', () => {

      const { deliveries, markersOrder } = this.state

      this.setState({ collapsed: false })

      if (deliveries.length > 0) {
        this.refresh(deliveries, markersOrder).then((state) => {
          if (state.route) {
            this.props.map.refreshRoute(user.username, state.route)
          }
          this.setState(state)
          this.props.onShow()
        })
      } else {
        this.props.onShow()
      }

    })
    $('#collapse-' + user.username).on('hidden.bs.collapse', () => {
      this.setState({ collapsed: true })
      this.props.onHide()
    })

    const container = findDOMNode(this).querySelector('.address-list')
    dragula([container], {

    }).on('drop', (element, target, source) => {

      const elements = target.querySelectorAll('.list-group-item')
      const markersOrder = _.map(elements, item => {
        return item.getAttribute('data-delivery') + '#' + item.getAttribute('data-address-type')
      })

      const { deliveries } = this.state
      this.refresh(deliveries, markersOrder).then((state) => {
        if (state.route) {
          this.props.map.refreshRoute(user.username, state.route)
        }
        this.props.map.zoom(user.username)
        this.setState(state)
      })
    })

  }

  refresh(deliveries, markersOrder) {

    const { map, user, save } = this.props

    return new Promise((resolve, reject) => {

      const markers = this.asMarkers(deliveries, markersOrder)

      this.setState({ loading: true })
      save(markers)
        .then(() => {
          this.setState({ loading: false })
          if (deliveries.length === 0) {
            resolve({
              duration: 0,
              distance: 0,
              deliveries: [],
              markersOrder: []
            })
          } else {
            map.route(markers)
              .then(route => {
                const duration = moment.utc()
                  .startOf('day')
                  .add(route.duration, 'seconds')
                  .format('HH:mm')
                const distance = (route.distance / 1000).toFixed(2) + ' Km'
                resolve({
                  duration,
                  distance,
                  deliveries,
                  markersOrder,
                  route
                })
              })
          }
        })
    })
  }

  add(delivery) {

    const { user } = this.props
    const { deliveries, markersOrder } = this.state

    deliveries.slice();
    deliveries.push(delivery);

    return this.refresh(deliveries, markersOrder).then((state) => {
      this.props.map.addMarkers(user.username, delivery)
      if (state.route) {
        this.props.map.refreshRoute(user.username, state.route)
      }
      this.props.map.zoom(user.username)
      this.setState(state)
    })
  }

  remove(delivery) {

    const { user } = this.props
    let { deliveries, markersOrder } = this.state

    deliveries = deliveries.slice()
    deliveries = _.filter(deliveries, item => item['@id'] !== delivery['@id'])

    return this.refresh(deliveries, []).then((state) => {

      this.props.map.removeMarkers(user.username, delivery)
      if (deliveries.length === 0) {
        this.props.map.clearPolyline(user.username)
      }
      if (state.route) {
        this.props.map.refreshRoute(user.username, state.route)
      }
      this.props.map.zoom(user.username)

      this.setState(state)
      this.props.onRemove(delivery)
    })
  }

  asMarkers(deliveries, markersOrder) {

    const markers = []
    deliveries.forEach(delivery => {

      const newMarkers = [{
        address: delivery.originAddress,
        type: 'pickup'
      }, {
        address: delivery.deliveryAddress,
        type: 'dropoff'
      }]

      newMarkers.forEach(newMarker => {
        markers.push({
          delivery,
          date: delivery.date,
          ...newMarker
        })
      })
    })

    markersOrder = markersOrder || []

    markers.sort((a, b) => {
      if (markersOrder.length > 0) {
        const keyA = markersOrder.indexOf(a.delivery['@id'] + '#' + a.type)
        const keyB = markersOrder.indexOf(b.delivery['@id'] + '#' + b.type)
        return keyA > keyB ? 1 : -1
      }
      return a.type === 'dropoff' ? 1 : -1
    })

    return markers
  }

  render() {

    const { user, map } = this.props
    const { deliveries, markersOrder, duration, distance, loading, collapsed } = this.state
    const markers = this.asMarkers(deliveries, markersOrder)

    return (
      <div className="panel panel-default" style={{ opacity: loading ? 0.7 : 1 }}>
        <div className="panel-heading">
          <h3 className="panel-title">
            <i className="fa fa-user"></i> 
            <a role="button" data-toggle="collapse" data-parent="#accordion" href={ '#collapse-' + user.username } aria-expanded="true"
              aria-controls="collapseOne">{ user.username }</a> 
            { collapsed && ( <i className="fa fa-caret-down"></i> ) }
            { !collapsed && ( <i className="fa fa-caret-up"></i> ) }
            { loading && (
              <span className="pull-right"><i className="fa fa-spinner"></i></span>
            )}
          </h3>
        </div>
        <div id={ 'collapse-' + user.username } className="panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne">
          { markers.length > 0 && (
            <div className="panel-body">
              <strong>Durée</strong>  <span>{ duration }</span>
              <br />
              <strong>Distance</strong>  <span>{ distance }</span>
            </div>
          )}
          <div className="list-group dropzone">
            <div className="list-group-item text-center dropzone-item">
              Déposez les livraisons ici
            </div>
          </div>
          <div className="list-group address-list">
            { markers.map(marker => {
                const key = marker.address['@id'] + marker.date
                const classNames = [
                  'list-group-item',
                  'list-group-item--' + marker.type,
                ]
                return (
                  <div key={ key } className={ classNames.join(' ') } data-delivery={ marker.delivery['@id'] } data-address-type={ marker.type }>
                    <span>#{ marker.delivery.id }</span> 
                    <i style={{ fontSize: '14px' }} className={ 'fa fa-' + (marker.type === 'pickup' ? 'arrow-up' : 'arrow-down') }></i>  
                    <a>
                      <span>{ marker.address.streetAddress }</span>
                      { marker.type === 'dropoff' && (
                        <span>
                          <br />
                          <span>{ moment(marker.date).format('lll') }</span>
                        </span>
                      ) }
                    </a>
                    <a href="#" className="address-remove" onClick={(e) => {
                      e.preventDefault()
                      this.remove(marker.delivery)
                    }}><i className="fa fa-times"></i></a>
                  </div>
                )
              })}
          </div>
        </div>
      </div>
    )
  }
}
