import React from 'react'
import moment from 'moment'
import { nowToPercentage, timeframeToPercentage } from '../redux/utils'

let listeners = []

const addListener = (cb) => listeners.push(cb)
const removeListener = (cb) => {
  listeners = listeners.filter(listener => listener !== cb)
}

setInterval(() => {
  listeners.forEach(listener => listener())
}, 1000)

class NowCursor extends React.Component {

  constructor(props) {
    super(props)

    this.state = {
      left: nowToPercentage() * 100,
    }

    this.tick = this.tick.bind(this)
  }

  componentDidMount () {
    addListener(this.tick)
  }

  componentWillUnmount() {
    removeListener(this.tick)
  }

  tick() {
    this.setState({
      left: nowToPercentage() * 100,
    })
  }

  render() {

    return (
      <span className="task__eta__now" style={{ left: `${this.state.left.toFixed(4)}%` }}></span>
    )
  }
}

class TaskEta extends React.Component {

  constructor(props) {
    super(props)

    this.ref = React.createRef()

    this.state = {
      width: 0,
      timeframeLeft: 0,
      timeframeWidth: 0,
    }
  }

  componentDidMount () {
    setTimeout(() => {
      const rect = this.ref.current.getBoundingClientRect()
      const { width } = rect
      this.setState({ width })
    }, 0)
  }

  render() {

    const { after, before } = this.props.task
    const { width } = this.state

    const [ percentAfter, percentBefore ] = timeframeToPercentage([ after, before ], moment(this.props.date))

    const timeframeLeft = width * percentAfter
    const timeframeWidth = ((width * percentBefore) - timeframeLeft)

    const isSameDay = moment(this.props.date).format('YYYY-MM-DD') === moment().format('YYYY-MM-DD')

    return (
      <span className="task__eta" ref={ this.ref }>
        <span className="task__eta__timeframe" style={{ left: timeframeLeft, width: timeframeWidth }}></span>
        { isSameDay && <NowCursor /> }
      </span>
    )
  }
}

export default TaskEta
