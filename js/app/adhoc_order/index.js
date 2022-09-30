import React from 'react'
import { render } from 'react-dom'
import { I18nextProvider } from 'react-i18next'
import Modal from 'react-modal'

import i18n from '../i18n'
import AdhocOrderForm from './AdhocOrderForm'

const container = document.getElementById('adhoc-order')

if (container) {
    Modal.setAppElement(container)

    render(
        <I18nextProvider i18n={ i18n }>
            <AdhocOrderForm taxCategories={JSON.parse(container.dataset.taxCategories)} />
        </I18nextProvider>,
        container
    )
}
