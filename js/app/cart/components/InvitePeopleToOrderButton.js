import React, { Component } from 'react'
import { withTranslation } from 'react-i18next'
import { connect } from 'react-redux'
import { openInvitePeopleToOrderModal } from '../redux/actions'

class InvitePeopleToOrderButton extends Component {

  render() {
    return (
      <button type="button" onClick={() => this.props.openInvitePeopleModal()} className="btn btn-md btn-success mt-4">
        <span>{this.props.loading && <i className="fa fa-spinner fa-spin mr-2"></i>}</span>
        <span>{this.props.t('INVITE_PEOPLE_TO_ADD_ITEMS')}</span>
      </button>
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
