import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

class CartErrors extends React.Component {

  render() {
    return (
      <div className="cart-wrapper__messages">
        { this.props.errors.map((message, key) => (
          <div key={ key } className="alert alert-warning">
            <span dangerouslySetInnerHTML={{ __html: message }}></span>
          </div>
        )) }
      </div>
    )
  }

}

function mapStateToProps(state) {

  let allMessages = []
  _.forEach(state.errors, (errors) => {
    allMessages = allMessages.concat(errors.map(error => error.message))
  })

  return {
    errors: allMessages,
  }
}

export default connect(mapStateToProps)(withTranslation()(CartErrors))

