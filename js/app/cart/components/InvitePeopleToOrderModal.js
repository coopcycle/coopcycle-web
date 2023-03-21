import React, { Component } from 'react'
import { withTranslation } from 'react-i18next'
import Modal from 'react-modal'
import { connect } from 'react-redux'
import ClipboardJS from 'clipboard'
import { closeInvitePeopleToOrderModal, invitePeopleToOrder } from '../redux/actions'

class InvitePeopleToOrderModal extends Component {

  componentDidMount() {
    const clipboard = new ClipboardJS('#invitation_link', {
      text: () => {
        return this.props.link
      },
    })

    clipboard.on('success', function (e) {
      console.info('Action:', e.action);
      console.info('Text:', e.text);
      console.info('Trigger:', e.trigger);

      e.clearSelection();
    });

  }

  afterOpen() {
    if (!this.props.link) {
      console.log('Generate link');
    }
  }

  render() {

    return (
      <Modal
        isOpen={this.props.isOpen}
        onAfterOpen={ this.afterOpen.bind(this) }
        onRequestClose={() => this.props.closeInvitePeopleToOrderModal() }
        contentLabel={this.props.t('INVITE_PEOPLOE_TO_ORDER_MODAL_LABEL')}
        className="ReactModal__Content--invite-people-to-order">
        <div className="text-center">
          <span className="text-monospace">{ this.props.link }</span>
          <a href="#" id="invitation_link" className="ml-2" onClick={ e => e.preventDefault() }><i className="fa fa-clipboard"></i></a>
        </div>
      </Modal>
    )
  }
}

function mapStateToProps(state) {

  return {
    isOpen: state.isInvitePeopleToOrderModalOpen,
    isRequesting: state.invitePeopleToOrderContext.isRequesting,
    hasError: state.invitePeopleToOrderContext.hasError,
    link: state.cart.invitationLink,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    closeInvitePeopleToOrderModal: () => dispatch(closeInvitePeopleToOrderModal()),
    invitePeopleToOrder: (emails) => dispatch(invitePeopleToOrder(emails)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(InvitePeopleToOrderModal))
