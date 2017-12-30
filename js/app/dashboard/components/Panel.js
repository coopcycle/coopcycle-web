import React from 'react'
import { findDOMNode } from 'react-dom'
import Dragula from 'react-dragula';

export default class extends React.Component {
  render() {
    return (
      <div className="dashboard__panel">
        { this.props.heading() }
        <div className="dashboard__panel__scroll">
          { this.props.children }
        </div>
      </div>
    )
  }
}
