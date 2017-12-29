import React from 'react'
import { findDOMNode } from 'react-dom'
import Dragula from 'react-dragula';

export default class extends React.Component {
  render() {
    return (
      <div className="dashboard__panel">
        <h4>
          <span>{ this.props.title }</span>
          { this.props.button && (
            <a href="#" className="pull-right" onClick={ e => {
              e.preventDefault();
              this.props.onClickButton()
            }}><i className="fa fa-plus"></i></a>
          )}
        </h4>
        <div className="dashboard__panel__scroll">
          { this.props.children }
        </div>
      </div>
    )
  }
}
