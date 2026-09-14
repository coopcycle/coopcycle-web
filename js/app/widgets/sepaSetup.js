import i18n from '../i18n'

function copyToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    return navigator.clipboard.writeText(text)
  }

  return new Promise((resolve, reject) => {
    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.style.position = 'fixed'
    textarea.style.opacity = '0'
    document.body.appendChild(textarea)
    textarea.focus()
    textarea.select()
    try {
      document.execCommand('copy')
      resolve()
    } catch (e) {
      reject(e)
    } finally {
      document.body.removeChild(textarea)
    }
  })
}

function initOwnerWidget(widget) {
  const trigger = widget.querySelector('[data-role="sepa-setup-trigger"]')
  const endpoint = widget.dataset.endpoint

  trigger.addEventListener('click', () => {
    trigger.setAttribute('disabled', 'disabled')

    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
    })
      .then(res => res.ok ? res.json() : Promise.reject(res))
      .then(({ url }) => {
        window.location.href = url
      })
      .catch(() => {
        trigger.removeAttribute('disabled')
        window.alert(i18n.t('SOMETHING_WENT_WRONG'))
      })
  })
}

function initAdminWidget(widget) {
  const endpoint = widget.dataset.endpoint
  const emailEndpoint = widget.dataset.emailEndpoint

  const copyBtn = widget.querySelector('[data-role="sepa-copy-link"]')
  if (copyBtn) {
    const originalLabel = copyBtn.innerHTML

    copyBtn.addEventListener('click', () => {
      copyBtn.setAttribute('disabled', 'disabled')

      fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
      })
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(({ url }) => copyToClipboard(url))
        .then(() => {
          copyBtn.innerHTML = '<i class="fa fa-check"></i> ' + i18n.t('SEPA_SETUP_LINK_COPIED')
        })
        .catch(() => {
          window.alert(i18n.t('SOMETHING_WENT_WRONG'))
        })
        .finally(() => {
          copyBtn.removeAttribute('disabled')
          setTimeout(() => {
            copyBtn.innerHTML = originalLabel
          }, 2000)
        })
    })
  }

  const modal = document.getElementById(widget.dataset.modal)

  if (modal) {
    const select = modal.querySelector('[data-role="sepa-email-select"]')
    const manualGroup = modal.querySelector('[data-role="sepa-email-manual-group"]')
    const manualInput = modal.querySelector('[data-role="sepa-email-manual-input"]')
    const sendBtn = modal.querySelector('[data-role="sepa-email-send"]')

    const syncManualVisibility = () => {
      manualGroup.classList.toggle('d-none', select.value !== '__manual__')
    }

    select.addEventListener('change', syncManualVisibility)
    syncManualVisibility()

    sendBtn.addEventListener('click', () => {
      const email = select.value === '__manual__' ? manualInput.value.trim() : select.value

      if (!email) {
        return
      }

      sendBtn.setAttribute('disabled', 'disabled')

      fetch(emailEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email }),
      })
        .then(res => res.ok ? res.json() : Promise.reject(res))
        .then(() => {
          window.$(modal).modal('hide')
          window.alert(i18n.t('SEPA_SETUP_EMAIL_SENT'))
        })
        .catch(() => {
          window.alert(i18n.t('SOMETHING_WENT_WRONG'))
        })
        .finally(() => {
          sendBtn.removeAttribute('disabled')
        })
    })
  }
}

export default function initSepaSetupWidgets() {
  document.querySelectorAll('[data-widget="sepa-setup-owner"]').forEach(initOwnerWidget)
  document.querySelectorAll('[data-widget="sepa-setup-admin"]').forEach(initAdminWidget)
}
