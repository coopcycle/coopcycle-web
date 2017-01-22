import React from 'react';
var moment = require('moment');

class OrderEvent extends React.Component
{
  constructor(props) {
    super(props);
  }
  render() {
    var cssClass = ['list-group-item'];
    if (this.props.active) {
      cssClass.push('active');
    }
    return (
      <div className={cssClass.join(' ')}>
        <small className="pull-right">{moment.unix(this.props.timestamp).format('D MMM HH:mm')}</small>
        <h4 className="list-group-item-heading">{this.props.eventName}</h4>
        <p className="list-group-item-text">
        </p>
      </div>
    );
  }
}

module.exports = OrderEvent;