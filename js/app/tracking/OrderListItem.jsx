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
  render() {
    var labelClassName = [
      'label'
    ];
    labelClassName.push(LABELS[this.props.state]);

    var itemClassName = [
      'list-group-item'
    ];
    if (this.props.active) {
      itemClassName.push('active');
    }

    return (
      <a className={itemClassName.join(' ')} href="#" onClick={this.props.onClick.bind(null, this.props.order)}>
        <h5 className="list-group-item-heading">
          <span style={{color: this.props.color}}>{this.props.id}</span>
        </h5>
        <p className="list-group-item-text">
          <span className={labelClassName.join(' ')}>{this.props.state}</span>
        </p>
      </a>
    );
  }
}

module.exports = OrderListItem;