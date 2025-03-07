import React from 'react'
import { render } from 'react-dom'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'

import i18n from '../i18n'

import LegalTextModal from "./LegalTextModal"

const init = async () => {
  const container = document.getElementById('termsAndConditionsAndPrivacyPolicy')

  if (!container) {
    return
  }

  const termsAndConditionsCheck = document.querySelector('[id$=_termsAndConditionsAndPrivacyPolicy_termsAndConditions]')
  const privacyPolicyCheck = document.querySelector('[id$=termsAndConditionsAndPrivacyPolicy_privacyPolicy]')

  Modal.setAppElement(container)

  render(
    <I18nextProvider i18n={i18n}>
      <LegalTextModal
        termsAndConditionsCheck={termsAndConditionsCheck}
        privacyPolicyCheck={privacyPolicyCheck} />
    </I18nextProvider>,
    container
  )
}

init()
