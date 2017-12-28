import React from 'react';
import StatusLabel from './StatusLabel'
import moment from 'moment'

class DeliveryListItem extends React.Component
{
  constructor(props) {
    super(props);
  }
  onClick(e) {
    e.preventDefault();
    this.props.onClick(this.props.delivery);
  }
  onClose(e) {
    e.preventDefault();
    e.stopPropagation();
    this.props.onClose();
  }
  render() {
    return (
      <a className="list-group-item" href="#"
        onClick={this.onClick.bind(this)}>
        <h5>
          <span>{ moment(this.props.date).format('lll') }</span>
          <small className="pull-right text-muted">#{ this.props.id }</small>
        </h5>
        <h6>
          <span>{this.props.originAddress.streetAddress}</span>
          <br />
          <span>{this.props.deliveryAddress.streetAddress}</span>
        </h6>
        <StatusLabel status={ this.props.status }  />
        { this.props.courier && (
          <small className="pull-right text-muted"><i className="fa fa-user"></i>  { this.props.courier }</small>
        )}
      </a>
    );
  }
}

module.exports = DeliveryListItem;
