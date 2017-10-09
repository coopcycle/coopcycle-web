import React from 'react';

const LABELS = {
  'WAITING': 'label-default',
  'DISPATCHING': 'label-info',
  'DELIVERING': 'label-success',
};

class DeliveryListItem extends React.Component
{
  constructor(props) {
    super(props);
  }
  onClick(e) {
    e.preventDefault();
    this.props.onClick(this.props.delivery);
  }
  onMouseEnter(e) {
    e.preventDefault();
    this.props.onMouseEnter(this.props.delivery);
  }
  onMouseLeave(e) {
    e.preventDefault();
    this.props.onMouseLeave();
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
      <li style={{borderColor: this.props.color}} className={itemClassName.join(' ')} href="#"
        onClick={this.onClick.bind(this)}
        onMouseEnter={this.onMouseEnter.bind(this)}
        onMouseLeave={this.onMouseLeave.bind(this)}>
        {closeButton}
        <h5>
          <span style={{color: this.props.color}}>{this.props.id}</span>
        </h5>
        <span className={labelClassName.join(' ')}>{this.props.state}</span>
      </li>
    );
  }
}

module.exports = DeliveryListItem;
