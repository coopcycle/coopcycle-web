import React from 'react'
import { connect } from 'react-redux'
import { translate } from 'react-i18next'

class CartErrors extends React.Component {

  render() {
    return (
      <div className="cart-wrapper__messages">
        { this.props.errors.map((message, key) => (
          <div key={ key } className="alert alert-warning">{ message }</div>
        )) }
      </div>
    )
  }

}

function mapStateToProps(state) {

  let allMessages = []
  _.forEach(state.errors, (errors, key) => {
    allMessages = allMessages.concat(errors.map(error => error.message))
  })

  return {
    errors: allMessages,
  }
}

export default connect(mapStateToProps)(translate()(CartErrors))

