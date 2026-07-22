import _ from 'lodash'
import axios from 'axios'
import './split-terms-and-privacy'

const emailInput = document.querySelector('[id$=_email]')
const usernameInput = document.querySelector('[id$=_username]')

// This entrypoint is loaded by form/registration.html.twig, which renders the DaisyUI
// validator structure (div.validator > label.input > input.grow + icons, followed by
// p.validator-hint), but also by _partials/profile/personal_information_form.html.twig,
// which renders plain form rows. There, the icons and the hint are simply absent, and
// only the aria-invalid attribute and #existing_user_error give feedback. So everything
// but the inputs themselves has to be treated as optional.
function getIcons(inputEl) {
  const wrapper = inputEl.parentElement  // label.input rendered by form/registration.html.twig
  return {
    spinner:   wrapper.querySelector('.loading-spinner'),
    checkIcon: wrapper.querySelector('.fa-check'),
    errorIcon: wrapper.querySelector('.fa-times'),
  }
}

function setHidden(el, hidden) {
  if (el) {
    el.hidden = hidden
  }
}

if (usernameInput && emailInput) {

  const { spinner: usernameSpinner, checkIcon: usernameCheckIcon, errorIcon: usernameErrorIcon } =
    getIcons(usernameInput)

  const { spinner: emailSpinner, checkIcon: emailCheckIcon, errorIcon: emailErrorIcon } =
    getIcons(emailInput)

  // p.validator-hint is the next sibling of div.validator (required by DaisyUI CSS ~)
  const usernameHint = usernameInput.closest('.validator')?.nextElementSibling ?? null

  const renderSuggestions = suggestions => {

    usernameHint.textContent = ''

    if (suggestions.length === 0) {
      return
    }

    const titleEl = document.createElement('strong')
    titleEl.textContent = 'Suggestions'
    titleEl.classList.add('mr-2')
    usernameHint.appendChild(titleEl)

    suggestions.forEach(suggestion => {
      const a = document.createElement('a')
      a.href = '#'
      a.classList.add('font-mono', 'mr-1')
      a.textContent = suggestion
      a.addEventListener('click', e => {
        e.preventDefault()
        usernameInput.value = a.textContent
        usernameInput.setAttribute('aria-invalid', 'false')
        setHidden(usernameErrorIcon, true)
        setHidden(usernameCheckIcon, false)
        setHidden(usernameHint, true)
      })
      usernameHint.appendChild(a)
    })

    setHidden(usernameHint, false)
  }

  const checkUsername = _.debounce(function() {

    const email = emailInput.value
    const username = usernameInput.value

    if (!username || username.length < 3) {
      return
    }

    usernameInput.removeAttribute('aria-invalid')
    setHidden(usernameSpinner, false)
    setHidden(usernameCheckIcon, true)
    setHidden(usernameErrorIcon, true)
    setHidden(usernameHint, true)

    axios.get('/register/suggest', { params: { username, email } }).then(({ data: result }) => {

      setHidden(usernameSpinner, true)

      if (result.exists) {
        // Only steal the focus back when there is something to fix. This runs half a
        // second after the last keystroke, by which time the user (or Cypress) has
        // usually moved on to the next field.
        usernameInput.focus()

        usernameInput.setAttribute('aria-invalid', 'true')
        setHidden(usernameErrorIcon, false)

        if (usernameHint) {
          renderSuggestions(result.suggestions)
        }
      } else {
        usernameInput.setAttribute('aria-invalid', 'false')
        setHidden(usernameCheckIcon, false)
      }
    })

  }, 500)

  const checkEmail = _.debounce(function() {

    emailInput.removeAttribute('aria-invalid')
    setHidden(emailSpinner, false)
    setHidden(emailCheckIcon, true)
    setHidden(emailErrorIcon, true)

    const errorEl = document.getElementById('existing_user_error')
    if (errorEl) {
      errorEl.classList.add('hidden')
    }

    axios.get('/register/check-email-exists', { params: { email: emailInput.value } }).then(({ data: result }) => {

      setHidden(emailSpinner, true)

      if (result.exists) {
        emailInput.setAttribute('aria-invalid', 'true')
        setHidden(emailErrorIcon, false)

        if (errorEl) {
          errorEl.classList.remove('hidden')
        }

        const usernameEl = document.querySelector('[name="_username"]')
        if (usernameEl) {
          usernameEl.value = emailInput.value
        }
      } else {
        emailInput.setAttribute('aria-invalid', 'false')
        setHidden(emailCheckIcon, false)
      }
    })

  }, 500)

  usernameInput
    .addEventListener('input', checkUsername, false)

  emailInput
    .addEventListener('input', () => {
      if (emailInput.checkValidity()) {
        checkEmail()
      }
    }, false)
}
