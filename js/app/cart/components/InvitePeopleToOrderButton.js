import React, { Component } from 'react'
import { withTranslation } from 'react-i18next'
import { connect } from 'react-redux'
import { openInvitePeopleToOrderModal } from '../redux/actions'

class InvitePeopleToOrderButton extends Component {

  render() {
    return (
      <div className="text-center mt-4">
        <a onClick={() => this.props.openInvitePeopleModal()}>
          <span>{this.props.t('INVITE_PEOPLE_TO_ADD_ITEMS')}</span>
        </a>
      </div>
    )
  }
}

function mapStateToProps(state) {

  return {
    loading: state.isFetching,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    openInvitePeopleModal: () => dispatch(openInvitePeopleToOrderModal()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(InvitePeopleToOrderButton))
