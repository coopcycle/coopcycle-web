import React from 'react'
import { render } from 'react-dom'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'

import i18n from '../i18n'

import AskForOptinsModal from "./AskForOptinsModal"
import createHttpClient from "../client"

const init = async () => {
    const container = document.getElementById('optins')

    if (!container) {
        return
    }

    Modal.setAppElement(container)

    const fetchToken = window.Routing.generate('profile_jwt')

    let result = null;
    let httpClient = null;

    try {
      result = await $.getJSON(fetchToken)
    } catch (err) {
      // when user is not logged in the request fails
    }

    if (result && result.jwt) {
      httpClient = createHttpClient(
        result.jwt,
        () => new Promise((resolve) => {
          $.getJSON(fetchToken).then(result => resolve(result.jwt))
        })
      )
    }

    const brandName = JSON.parse(container.dataset.brandName)

    render(
        <I18nextProvider i18n={ i18n }>
          <AskForOptinsModal httpClient={httpClient} brandName={brandName} />
        </I18nextProvider>,
        container
      )
}

init()
