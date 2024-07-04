import React, { Component } from 'react'
import { withTranslation } from 'react-i18next'
import Modal from 'react-modal'
import { connect } from 'react-redux'
import ClipboardJS from 'clipboard'
import { closeInvitePeopleToOrderModal, createInvitation } from '../redux/actions'
import classNames from "classnames";

class InvitePeopleToOrderModal extends Component {


  constructor(props) {
    super(props);
    this.state = {
      copied: false,
    }
  }

  componentDidMount() {
    const clipboard = new ClipboardJS('#copy-button', {
      text: () => {
        return this.generateLink()
      },
    })

    clipboard.on('success', (e) => {
      this.setState({copied: true})
      e.clearSelection();
    });

  }

  afterOpen() {
    if (!this.props.invitation) {
      this.props.createInvitation()
    }
  }

  generateLink() {
    if (this.state.isRequesting || !this.props.invitation) {
      return null;
    }
    return location.protocol + '//' + location.host + window.Routing.generate('public_share_order', {slug: this.props.invitation})
  }

  render() {
    return (
      <Modal
        isOpen={this.props.isOpen}
        onAfterOpen={ this.afterOpen.bind(this) }
        onRequestClose={() => this.props.closeInvitePeopleToOrderModal() }
        contentLabel={this.props.t('INVITE_PEOPLE_TO_ADD_ITEMS')}
        className="ReactModal__Content--invite-people-to-order">
        <div className="text-center w-50 mx-auto p-3">
          <div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" width="228" viewBox="1028 -1131 228 108" height="108"><g fill="#d9edf7"><ellipse rx="25.979" ry="25.932" cx="1053.979" cy="-1048.932"/><ellipse rx="25.979" ry="25.932" cx="1230.021" cy="-1048.932"/><path d="M1084.236-1051.678h99.635v3.051h-99.635zm60.785-50.184l59.022-1.07-18.849 44.542h-35.553l-4.62-43.472zm-87.681-22.426h22.617v6.712h-22.617zm20.783 22.576h61.126v4.271h-61.126zm42.788-29.288h6.724v6.712h-6.724z"/></g><g strokeWidth="2" stroke="#337ab7"><path d="M1192.86-1049.317s.625-33.658 37.036-35.84m-140.77 26.767l-23.228-53.695-11.614 64.068 34.842-10.373z"/><path d="M1123.968-1122.458l20.171 63.458"/></g></svg>
          </div>
          <h2>{ this.props.t('GROUP_ORDER_START') }</h2>
          <p>{ this.props.t('GROUP_ORDER_SHARE_LINK') }</p>
            <div className="input-group">
              <input type="text" className="form-control" defaultValue={this.generateLink()} />
            <span className="input-group-btn">
              <button className={classNames("btn", {"btn-primary": !this.state.copied, "btn-success": this.state.copied})}
                      disabled={this.props.isRequesting}
                      type="button" id="copy-button"
                      title="Copy to Clipboard">
                { this.props.isRequesting && <span><i className="fa fa-spinner fa-spin"></i></span>}
                { this.state.copied && <span><i className="fa fa-check"></i> { this.props.t('COPIED') }</span>}
                { !this.state.copied && <span>{ this.props.t('COPY') }</span>}
              </button>
            </span>
            </div>
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
    invitation: state.cart.invitation,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    closeInvitePeopleToOrderModal: () => dispatch(closeInvitePeopleToOrderModal()),
    createInvitation: () => dispatch(createInvitation()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(InvitePeopleToOrderModal))
