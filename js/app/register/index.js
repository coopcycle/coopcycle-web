import _ from 'lodash'
import './split-terms-and-privacy'

const emailInput =
  document.querySelector('[id$=_email]')

const usernameInput =
  document.querySelector('[id$=_username]')

/* */

const checkUsername = _.debounce(function() {

  const email =
    document.querySelector('[id$=_email]').value

  const username =
    document.querySelector('[id$=_username]').value

  if (!username || username.length < 3) {
    return
  }

  const formGroup = usernameInput.closest('.form-group')

  formGroup.classList.remove('has-success', 'has-error')
  formGroup.classList.add('has-feedback')

  let feedbackEl =
    formGroup.querySelector('.form-control-feedback')

  let suggestionsEl =
    formGroup.querySelector('.username-suggestions')

  if (!feedbackEl) {
    feedbackEl = document.createElement('span')
    feedbackEl.classList.add('fa', 'form-control-feedback')
    feedbackEl.setAttribute('aria-hidden', 'true')
    usernameInput.parentNode.insertBefore(feedbackEl, usernameInput.nextSibling)
  }

  if (!suggestionsEl) {
    suggestionsEl = document.createElement('div')
    suggestionsEl.classList.add('help-block', 'username-suggestions')
    formGroup.insertBefore(suggestionsEl, formGroup.querySelector('.help-block'))
  }

  feedbackEl.classList.remove('fa-check', 'fa-warning')
  feedbackEl.classList.add('fa-spinner', 'fa-spin')

  $.getJSON('/register/suggest', { username, email }).then(result => {

    feedbackEl.classList.remove('fa-spinner', 'fa-spin')

    usernameInput.focus()

    if (usernameInput.value) {
      formGroup.classList
        .add(result.exists ? 'has-error' : 'has-success')
      feedbackEl.classList.add(result.exists ? 'fa-warning' : 'fa-check')
    }

    suggestionsEl.textContent = '';

    const titleEl = document.createElement('strong')
    titleEl.innerHTML = 'Suggestions'
    titleEl.classList.add('mr-2')
    suggestionsEl.appendChild(titleEl)

    result.suggestions.forEach(suggestion => {

      const suggestionEl = document.createElement('a')

      suggestionEl.setAttribute('href', '#')
      suggestionEl.classList.add('text-monospace')
      suggestionEl.innerHTML = suggestion
      suggestionEl.addEventListener('click', (e) => {
        e.preventDefault()
        usernameInput.value = e.currentTarget.textContent
        feedbackEl.classList.remove('fa-warning')
        feedbackEl.classList.add('fa-check')
        formGroup.classList.remove('has-error')
        formGroup.classList.add('has-success')
        suggestionsEl.textContent = '';
      }, false)

      suggestionsEl.appendChild(suggestionEl)
    })

  })

}, 500)

const checkEmail = _.debounce(function() {

  const formGroup = emailInput.closest('.form-group')

  formGroup.classList.remove('has-success', 'has-error')
  formGroup.classList.add('has-feedback')

  const errorEl = document.getElementById('existing_user_error')

  if (errorEl) {
    errorEl.classList.add('hidden')
  }

  let feedbackEl =
    formGroup.querySelector('.form-control-feedback')

  if (!feedbackEl) {
    feedbackEl = document.createElement('span')
    feedbackEl.classList.add('fa', 'form-control-feedback')
    feedbackEl.setAttribute('aria-hidden', 'true')
    emailInput.parentNode.insertBefore(feedbackEl, emailInput.nextSibling)
  }

  feedbackEl.classList.remove('fa-check', 'fa-warning')
  feedbackEl.classList.add('fa-spinner', 'fa-spin')

  $.getJSON('/register/check-email-exists', { email: emailInput.value }).then(result => {

    feedbackEl.classList.remove('fa-spinner', 'fa-spin')

    if (result.exists) {
      if (errorEl) {
        errorEl.classList.remove('hidden')
      }

      const usernameEl = document.querySelector('[name="_username"]')

      if (usernameEl) {
        usernameEl.value = emailInput.value
      }
    }

    formGroup.classList.add(result.exists ? 'has-error' : 'has-success')
    feedbackEl.classList.add(result.exists ? 'fa-warning' : 'fa-check')
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
