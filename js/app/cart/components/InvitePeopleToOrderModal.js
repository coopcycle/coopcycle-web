import { Formik } from 'formik'
import React, { Component } from 'react'
import { withTranslation } from 'react-i18next'
import Modal from 'react-modal'
import { connect } from 'react-redux'
import { closeInvitePeopleToOrderModal, invitePeopleToOrder } from '../redux/actions'

class InvitePeopleToOrderModal extends Component {

  _validate(values) {
    const errors = {}

    if (!values.emails) {
      errors.emails = this.props.t('ADD_AT_LEAST_ONE_EMAIL')
    } else {
      const emails = values.emails.split(",")
      const someEmailIsInvalid = emails.some(email => !/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i.test(email.trim()))
      if (someEmailIsInvalid) {
        errors.emails = this.props.t('ONE_OR_MORE_EMAIL_IS_INVALID')
      }
    }

    return errors
  }

  _onSubmit(values) {
    const emails = values.emails.split(",").map((email) => email.trim())
    this.props.invitePeopleToOrder(emails)
  }

  render() {

    const initialValues = {
      emails: '',
    }

    return (
      <Modal
        isOpen={this.props.isOpen}
        onRequestClose={() => this.props.closeInvitePeopleToOrderModal() }
        contentLabel={this.props.t('INVITE_PEOPLOE_TO_ORDER_MODAL_LABEL')}
        className="ReactModal__Content--invite-people-to-order">
        <div>
          <Formik
            initialValues={ initialValues }
            validate={ this._validate.bind(this) }
            onSubmit={ this._onSubmit.bind(this) }
            validateOnBlur={ false }
            validateOnChange={ false }>
              {({
            values,
            errors,
            touched,
            handleSubmit,
            handleChange,
            handleBlur,
          }) => (
            <div>
              <div className="modal-header">
                <button type="button" className="close" onClick={ () => this.props.closeInvitePeopleToOrderModal() } aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
                <h4 className="modal-title">{ this.props.t('ADD_GUESTS_EMAILS_TITLE') }</h4>
              </div>
              <form onSubmit={ handleSubmit } autoComplete="off" className="modal-body form">
                <div className={ errors.emails && touched.emails ? 'form-group has-error mb-0' : 'form-group mb-0' }>
                  <input type="text" className="form-control" name="emails"
                    onChange={ handleChange }
                    onBlur={ handleBlur }
                    value={ values.emails } />
                  { errors.emails && touched.emails && (
                    <small className="help-block">{ errors.emails }</small>
                  ) }
                </div>
                <small className="help-block mb-2">{ this.props.t('ENTER_EMAILS_SEPARATED_BY_COMMA') }</small>

                {
                  (!this.props.isRequesting && this.props.hasError) ?
                  <div className="alert alert-danger">
                    { this.props.t('SEND_INVITATIONS_FAILURE') }
                  </div> : null
                }

                <button type="submit" className="btn btn-primary mt-4" disabled={this.props.isRequesting}>
                  <span>{this.props.isRequesting && <i className="fa fa-spinner fa-spin mr-2"></i>}</span>
                  <span>{ this.props.t('SEND_INVITATIONS') }</span>
                </button>
              </form>
            </div>
          )}
          </Formik>
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
  }
}

function mapDispatchToProps(dispatch) {

  return {
    closeInvitePeopleToOrderModal: () => dispatch(closeInvitePeopleToOrderModal()),
    invitePeopleToOrder: (emails) => dispatch(invitePeopleToOrder(emails)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(InvitePeopleToOrderModal))
