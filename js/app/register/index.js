import _ from 'lodash'
import axios from 'axios'
import './split-terms-and-privacy'

const emailInput = document.querySelector('[id$=_email]')
const usernameInput = document.querySelector('[id$=_username]')

function getIcons(inputEl) {
  const wrapper = inputEl.parentElement  // label.input rendered by form/registration.html.twig
  return {
    spinner:   wrapper.querySelector('.loading-spinner'),
    checkIcon: wrapper.querySelector('.fa-check'),
    errorIcon: wrapper.querySelector('.fa-times'),
  }
}

const { spinner: usernameSpinner, checkIcon: usernameCheckIcon, errorIcon: usernameErrorIcon } =
  getIcons(usernameInput)

const { spinner: emailSpinner, checkIcon: emailCheckIcon, errorIcon: emailErrorIcon } =
  getIcons(emailInput)

// p.validator-hint is the next sibling of div.validator (required by DaisyUI CSS ~)
const usernameHint = usernameInput.closest('.validator').nextElementSibling

/* */

const checkUsername = _.debounce(function() {

  const email = emailInput.value
  const username = usernameInput.value

  if (!username || username.length < 3) {
    return
  }

  usernameInput.removeAttribute('aria-invalid')
  usernameSpinner.hidden = false
  usernameCheckIcon.hidden = true
  usernameErrorIcon.hidden = true
  usernameHint.hidden = true

  axios.get('/register/suggest', { params: { username, email } }).then(({ data: result }) => {

    usernameSpinner.hidden = true
    usernameInput.focus()

    if (result.exists) {
      usernameInput.setAttribute('aria-invalid', 'true')
      usernameErrorIcon.hidden = false

      usernameHint.textContent = ''

      if (result.suggestions.length > 0) {
        const titleEl = document.createElement('strong')
        titleEl.textContent = 'Suggestions'
        titleEl.classList.add('mr-2')
        usernameHint.appendChild(titleEl)

        result.suggestions.forEach(suggestion => {
          const a = document.createElement('a')
          a.href = '#'
          a.classList.add('font-mono', 'mr-1')
          a.textContent = suggestion
          a.addEventListener('click', e => {
            e.preventDefault()
            usernameInput.value = a.textContent
            usernameInput.setAttribute('aria-invalid', 'false')
            usernameErrorIcon.hidden = true
            usernameCheckIcon.hidden = false
            usernameHint.hidden = true
          })
          usernameHint.appendChild(a)
        })

        usernameHint.hidden = false
      }
    } else {
      usernameInput.setAttribute('aria-invalid', 'false')
      usernameCheckIcon.hidden = false
    }
  })

}, 500)

const checkEmail = _.debounce(function() {

  emailInput.removeAttribute('aria-invalid')
  emailSpinner.hidden = false
  emailCheckIcon.hidden = true
  emailErrorIcon.hidden = true

  const errorEl = document.getElementById('existing_user_error')
  if (errorEl) {
    errorEl.classList.add('hidden')
  }

  axios.get('/register/check-email-exists', { params: { email: emailInput.value } }).then(({ data: result }) => {

    emailSpinner.hidden = true

    if (result.exists) {
      emailInput.setAttribute('aria-invalid', 'true')
      emailErrorIcon.hidden = false

      if (errorEl) {
        errorEl.classList.remove('hidden')
      }

      const usernameEl = document.querySelector('[name="_username"]')
      if (usernameEl) {
        usernameEl.value = emailInput.value
      }
    } else {
      emailInput.setAttribute('aria-invalid', 'false')
      emailCheckIcon.hidden = false
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
