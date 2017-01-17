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

    var itemClassName = [];
    var closeButton = (
      <span></span>
    );
    if (this.props.active) {
      itemClassName.push('active');
      closeButton = (
        <span style={{color: this.props.color}} type="button" className="close pull-right" onClick={this.onClose.bind(this)}>
          <span aria-hidden="true">&times;</span>
        </span>
      );
    }

    return (
      <li style={{borderColor: this.props.color}} className={itemClassName.join(' ')} href="#" onClick={this.onClick.bind(this)}>
        {closeButton}
        <h5>
          <span style={{color: this.props.color}}>{this.props.id}</span>
        </h5>
        <span className={labelClassName.join(' ')}>{this.props.state}</span>
      </li>
    );
  }
}

module.exports = OrderListItem;