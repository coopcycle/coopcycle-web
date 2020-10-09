import _ from 'lodash'

const emailInput =
  document.querySelector('[name="fos_user_registration_form[email]"]')

const usernameInput =
  document.querySelector('[name="fos_user_registration_form[username]"]')

const formGroup = usernameInput.closest('.form-group')

/* */

const checkUsername = _.debounce(function() {

  const email =
    document.querySelector('[name="fos_user_registration_form[email]"]').value

  const username =
    document.querySelector('[name="fos_user_registration_form[username]"]').value

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

  usernameInput.setAttribute('disabled', true)

  $.getJSON('/register/suggest', { username, email }).then(result => {

    feedbackEl.classList.remove('fa-spinner', 'fa-spin')

    usernameInput.setAttribute('disabled', false)
    usernameInput.removeAttribute('disabled')

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

}, 350)

usernameInput
  .addEventListener('input', checkUsername, false)

emailInput
  .addEventListener('input', () => {
    if (emailInput.checkValidity()) {
      checkUsername()
    }
  }, false)
