import React, { Component } from 'react'
import moment from 'moment'
import i18n from '../i18n'
import _ from 'lodash'
import TimelineStep from './TimelineStep'

moment.locale($('html').attr('lang'))

export default class extends Component {

  constructor(props) {
    super(props)

    const events = this.props.events.sort((a, b) => moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1)

    this.state = {
      order: this.props.order,
      events,
    }
  }

  addEvent(event) {
    const { events } = this.state

    let newEvents = events.slice(0)
    newEvents.push(event)
    newEvents = newEvents.sort((a, b) => moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1)

    this.setState({ events: newEvents })
  }

  updateOrder(order) {
    this.setState({ order })
  }

  renderEvent(event, key) {
    switch (event.name) {
    case 'order:created':
      return (
        <TimelineStep
          success
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_CREATED_TITLE', { date: moment(event.createdAt).format('LT') }) } />
      )
    case 'order:accepted':
      return (
        <TimelineStep
          success
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_ACCEPTED_TITLE', { date: moment(event.createdAt).format('LT') }) }
          description={ 'Description' } />
      )
    case 'order:refused':
      return (
        <TimelineStep
          danger
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_REFUSED_TITLE', { date: moment(event.createdAt).format('LT') }) }
          description={ 'Description' } />
      )
    case 'order:cancelled':
      return (
        <TimelineStep
          danger
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_CANCELLED_TITLE', { date: moment(event.createdAt).format('LT') }) } />
      )
    case 'order:picked':
      return (
        <TimelineStep
          success
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_PICKED_TITLE', { date: moment(event.createdAt).format('LT') }) }
          description={ 'Description' } />
      )
    case 'order:dropped':
      return (
        <TimelineStep
          success
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_DROPPED_TITLE', { date: moment(event.createdAt).format('LT') }) }
          description={ 'Description' } />
      )
    }
  }

  renderNextEvent() {

    const { events } = this.state
    const last = _.last(events)

    switch (last.name) {
    case 'order:created':
      return (
        <TimelineStep active
          title={ i18n.t('ORDER_TIMELINE_AFTER_CREATED_TITLE') }
          description={ i18n.t('ORDER_TIMELINE_AFTER_CREATED_DESCRIPTION') } />
      )
    case 'order:accepted':
      return (
        <TimelineStep active
          title={ i18n.t('ORDER_TIMELINE_AFTER_ACCEPTED_TITLE') }
          description={ i18n.t('ORDER_TIMELINE_AFTER_ACCEPTED_DESCRIPTION') } />
      )
    case 'order:picked':
      return (
        <TimelineStep active
          title={ i18n.t('ORDER_TIMELINE_AFTER_PICKED_TITLE') }
          description={ i18n.t('ORDER_TIMELINE_AFTER_PICKED_DESCRIPTION') } />
      )
    }
  }

  renderTimeline() {
    return (
      <div className="order-timeline">
        { this.state.events.map((event, key) => this.renderEvent(event, key)) }
        { this.renderNextEvent() }
      </div>
    )
  }

  render () {

    const { order } = this.state

    const deliveryMoment = moment(order.shippedAt)
    const deliveryTime = deliveryMoment.format('LT')
    const formattedDeliveryDate = deliveryMoment.format('dddd D MMMM')
    const deliveryIsToday = formattedDeliveryDate === moment(Date.now()).format('dddd D MMMM')

    let message
    if (deliveryIsToday) {
      message = i18n.t('ORDER_FOLLOW_DELIVERY_EXPECTED_AT', { deliveryTime: deliveryTime })
    } else {
      message = i18n.t('ORDER_FOLLOW_DELIVERY_EXPECTED_AT_WITH_DATE', {
        deliveryTime: deliveryTime,
        deliveryDate: formattedDeliveryDate
      })
    }

    return (
      <div>
        <p>{ message }</p>
        <hr/>
        { this.renderTimeline() }
      </div>
    )
  }
}
