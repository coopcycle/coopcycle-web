import React, { Component } from 'react'
import moment from 'moment'
import _ from 'lodash'

import i18n from '../i18n'
import TimelineStep from './TimelineStep'
import ShippingTimeRange from '../components/ShippingTimeRange'

moment.locale($('html').attr('lang'))

const dateComparator = (a, b) => moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1

export default class extends Component {

  constructor(props) {
    super(props)

    const events = this.props.events.sort(dateComparator)

    this.state = {
      order: props.order,
      events,
    }
  }

  addEvent(event) {
    const { events } = this.state

    let newEvents = events.slice(0)
    newEvents.push(event)

    newEvents = newEvents.sort(dateComparator)

    this.setState({ events: newEvents })
  }

  updateOrder(order) {
    this.setState({ order })
  }

  renderEvent(event, key) {

    const date = moment(event.createdAt).format('LT')

    switch (event.name) {
    case 'order:created':
      return (
        <TimelineStep
          success
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_CREATED_TITLE', { date }) } />
      )
    case 'order:accepted':
      return (
        <TimelineStep
          success
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_ACCEPTED_TITLE', { date }) }
          description={ 'Description' } />
      )
    case 'order:refused':
      return (
        <TimelineStep
          danger
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_REFUSED_TITLE', { date }) }
          description={ 'Description' } />
      )
    case 'order:cancelled':
      return (
        <TimelineStep
          danger
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_CANCELLED_TITLE', { date  }) } />
      )
    case 'order:picked':
      return (
        <TimelineStep
          success
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_PICKED_TITLE', { date }) }
          description={ 'Description' } />
      )
    case 'order:dropped':
      return (
        <TimelineStep
          success
          key={ key }
          title={ i18n.t('ORDER_TIMELINE_DROPPED_TITLE', { date }) }
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
        <TimelineStep active spinner
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

    return (
      <div className="border mb-3">
        <h4 className="bg-light p-3 m-0">
          <ShippingTimeRange value={ order.shippingTimeRange } />
        </h4>
        <div className="px-3 py-4">
          { this.renderTimeline() }
        </div>
      </div>
    )
  }
}
