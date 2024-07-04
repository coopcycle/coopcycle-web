import React, { Component } from 'react'
import { withTranslation } from 'react-i18next'
import Modal from 'react-modal'

import './index.scss'

class AskForOptinsModal extends Component {

  constructor(props) {
    super(props)
    this.state = {
        isOpen: false,
        user: null,
        optins: [],
        optinsToAsk: [],
    }

    this._onCheckboxChange = this._onCheckboxChange.bind(this);
    this._onClickSubmit = this._onClickSubmit.bind(this);
  }

  async componentDidMount() {
    if (this.props.httpClient) {
      await this._loadUser()
    }
  }

  _loadUser() {
    return this.props.httpClient.get('/api/me')
      .then((result) => {
        this.setState({user: result.data}, () => this._loadUserOptins())
      })
  }

  _loadUserOptins() {
    if (this.state.user && this.state.user.roles.length && this.state.user.roles.includes('ROLE_USER')) {
      return this.props.httpClient.get('/api/me/optin-consents')
        .then((result) => {
          this.setState({optins: result.data['hydra:member']}, () => this._handleUserOptins())
        })
        .catch(() => {
          //FIXME; how should we handle it? Silently fail for now to prevent cypress tests from failing
        })
    }
  }

  _handleUserOptins() {
    if (this.state.optins.length) {
      const optinsToAsk = this.state.optins.filter((optin) => !optin.asked);
      this.setState({optinsToAsk, isOpen: !!optinsToAsk.length})
    }
  }

  _onCheckboxChange(optin) {
    this.setState({optinsToAsk: this.state.optinsToAsk.map((opt) => {
      if (opt.id === optin.id) opt.accepted = !opt.accepted
      return opt
    })})
  }

  _onClickSubmit() {
    const optinsToSubmit = this.state.optinsToAsk.map((opt) => {
      opt.asked = true
      return this.props.httpClient.put('/api/me/optin-consents', opt)
    })
    return Promise.all(optinsToSubmit)
      .then(() => this.setState({isOpen: false}))
      .catch((err) => {
        console.error(`Can not update optin consents - err: ${err}`)
        this.setState({isOpen: false})
      })

  }

  render() {

    return (
      <Modal
        isOpen={ this.state.isOpen }
        shouldCloseOnOverlayClick={ false }
        contentLabel={ this.props.t('SELECT_OPTIN_CONSENTS') }
        className="ReactModal__Content--optins"
        overlayClassName="ReactModal__Overlay--optins">
        <form name="optins">
          <h4 className="text-center">{ this.props.t('OPTIN_CONSENT_MODAL_TITLE') }</h4>
          <p className="text-muted">{ this.props.t('OPTIN_CONSENT_MODAL_DISCLAIMER') }</p>
          {this.state.optinsToAsk.length && this.state.optinsToAsk.map((optin) => {
              return (
                <div className="checkbox" key={optin.id}>
                  <label>
                    <input type="checkbox" onChange={() => this._onCheckboxChange(optin)}/>
                      { this.props.t(`OPTIN_CONSENT_${optin.type}_LABEL`, {brand: this.props.brandName}) }
                  </label>
                </div>
              )
          })}
          <button type="button" className="btn btn-block btn-default" onClick={ () => this._onClickSubmit() }>
            {this.props.t('OPTIN_CONSENT_MODAL_SUBMIT')}
          </button>
        </form>
      </Modal>
    )
  }
}

export default withTranslation()(AskForOptinsModal)
