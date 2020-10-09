import _ from 'lodash'

const checkUsername = _.debounce(function() {

  const email =
    document.querySelector('[name="fos_user_registration_form[email]"]').value

  const username =
    document.querySelector('[name="fos_user_registration_form[username]"]').value

  const usernameInput =
      document.querySelector('[name="fos_user_registration_form[username]"]')

  const formGroup = usernameInput.closest('.form-group')

  formGroup.classList.remove('has-success', 'has-error')
  formGroup.classList.add('has-feedback')

  let feedbackEl =
    formGroup.querySelector('.form-control-feedback')

  if (!feedbackEl) {
    feedbackEl = document.createElement('span')
    feedbackEl.classList.add('fa', 'form-control-feedback')
    feedbackEl.setAttribute('aria-hidden', 'true')
    usernameInput.parentNode.insertBefore(feedbackEl, usernameInput.nextSibling)
  }

  feedbackEl.classList.remove('fa-check', 'fa-warning')
  feedbackEl.classList.add('fa-spinner', 'fa-spin')

  usernameInput.setAttribute('disabled', true)

  $.getJSON('/register/suggest', { username, email }).then(result => {

    feedbackEl.classList.remove('fa-spinner', 'fa-spin')

    usernameInput.setAttribute('disabled', false)
    usernameInput.removeAttribute('disabled')

    formGroup
      .classList
      .add(result.exists ? 'has-error' : 'has-success')

    feedbackEl.classList.add(result.exists ? 'fa-warning' : 'fa-check')

  })

}, 350)

document.querySelector('[name="fos_user_registration_form[username]"]').addEventListener('input', checkUsername, false)
