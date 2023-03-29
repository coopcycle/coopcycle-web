import React from 'react'
import {moment} from "../../shared";
import i18next from "../i18n"

export default class OrdersRateLimiter extends React.Component {

  constructor(props) {
    super(props);
    const [amount, time] = props.defaultValue.split(':')
    this.state = {
      amount,
      time
    }
  }
  componentDidUpdate() {
    const {amount, time} = this.state
    this.props.onChange(`${amount}:${time}`)
  }

  enabled() {
    return this.state.amount !== '' && this.state.time !== ''
  }

  limitColor() {
    const ratio = parseInt(this.state.amount, 10) / parseInt(this.state.time, 10)
    return ratio > 1 ? "text-warning" : ""
  }

  render() {
    return (
      <div className="form-group">
        <div className="row">
          <div className="col-md-3 form-group">
            <div className="input-group">
              <input type="number"
                     value={this.state.amount}
                     placeholder="5"
                     onChange={e => this.setState({amount: e.target.value})}
                     className="form-control"/>
              <span className="input-group-addon"><small>{i18next.t('ORDERS_RATE_LIMIT.ORDER_UNIT')}</small></span>
            </div>
          </div>
          <div className="col-md-3 form-group">
            <div className="input-group">
              <input type="number"
                     value={this.state.time}
                     onChange={e => this.setState({time: e.target.value})}
                     placeholder="10"
                     className="form-control"/>
              <span className="input-group-addon"><small>{i18next.t('ORDERS_RATE_LIMIT.TIME_UNIT')}</small></span>
            </div>
          </div>
        </div>
        {this.enabled() && <div className={this.limitColor()}>{i18next.t('ORDERS_RATE_LIMIT.LIMIT', {
          command: this.state.amount,
          time: moment.duration(this.state.time, 'minutes').humanize()
        })}</div>}
        {!this.enabled() && <div>{i18next.t('ORDERS_RATE_LIMIT.NO_LIMIT')}</div>}
      </div>
    )
  }

}
