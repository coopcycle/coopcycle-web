import React from 'react'

const GREEN = '#27ae60'
const BLUE = '#337ab7'

export default class extends React.Component {
  render() {

    const color = this.props.task.type === 'DROPOFF' ? GREEN : BLUE

    return (
      <i className="fa fa-lg fa-map-marker" style={{ color }}></i>
    )
  }
}
