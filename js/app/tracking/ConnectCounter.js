import React, { Component } from 'react';

export default class ConnectCounter extends Component
{
  constructor(props) {
    super(props);
    this.state = {
      count: 0
    };
  }
  increment() {
    let { count } = this.state
    this.setState({ count: ++count })
  }
  decrement() {
    let { count } = this.state
    this.setState({ count: --count })
  }
  render() {

    return (
      <h4>
        <i className="fa fa-bicycle"></i>  ({ this.state.count })
      </h4>
    );
  }
}
