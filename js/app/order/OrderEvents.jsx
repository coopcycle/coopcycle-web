import React from 'react';
import OrderEvent from './OrderEvent.jsx';
import _ from 'underscore';

class OrderEvents extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      events: props.events,
    };
  }
  add(event) {
    let events = this.state.events;
    events.push(event);

    this.setState({events});
  }
  render() {

    var last = _.last(this.state.events);
    var items = _.map(this.state.events, (event) => {
      return (
        <OrderEvent
          key={event.timestamp}
          eventName={event.eventName}
          timestamp={event.timestamp}
          active={last === event} />
      );
    });

    return (
      <div className="panel panel-default">
        <div className="panel-heading">{this.props.i18n['History']}</div>
        <div className="list-group">
          {items}
        </div>
      </div>
    );
  }
}

module.exports = OrderEvents;