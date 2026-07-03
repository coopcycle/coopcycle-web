import React from 'react'
import { createRoot } from 'react-dom/client'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'
import axios from 'axios'

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
      result = (await axios.get(fetchToken)).data
    } catch (err) {
      // when user is not logged in the request fails
    }

    if (result && result.jwt) {
      httpClient = createHttpClient(
        result.jwt,
        () => new Promise((resolve) => {
          axios.get(fetchToken).then(res => resolve(res.data.jwt))
        })
      )
    }

    const brandName = JSON.parse(container.dataset.brandName)

    createRoot(container).render(
        <I18nextProvider i18n={ i18n }>
          <AskForOptinsModal httpClient={httpClient} brandName={brandName} />
        </I18nextProvider>
      )
}

init()
