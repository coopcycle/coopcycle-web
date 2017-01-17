import React from 'react';

const LABELS = {
  'WAITING': 'label-default',
  'DISPATCHING': 'label-info',
  'DELIVERING': 'label-success',
}

class OrderListItem extends React.Component
{
  constructor(props) {
    super(props);
  }
  onClick(e) {
    e.preventDefault();
    this.props.onClick(this.props.order);
  }
  onClose(e) {
    e.preventDefault();
    e.stopPropagation();
    this.props.onClose();
  }
  render() {
    var labelClassName = [
      'label'
    ];
    labelClassName.push(LABELS[this.props.state]);

    var itemClassName = [
      'list-group-item'
    ];
    var closeButton = (
      <span></span>
    );
    if (this.props.active) {
      itemClassName.push('active');
      closeButton = (
        <span type="button" className="close pull-right" onClick={this.onClose.bind(this)}>
          <span aria-hidden="true">&times;</span>
        </span>
      );
    }

    return (
      <a className={itemClassName.join(' ')} href="#" onClick={this.onClick.bind(this)}>
        <h5 className="list-group-item-heading">
          <span style={{color: this.props.color}}>{this.props.id}</span>
          {closeButton}
        </h5>
        <p className="list-group-item-text">
          <span className={labelClassName.join(' ')}>{this.props.state}</span>
        </p>
      </a>
    );
  }
}

module.exports = OrderListItem;