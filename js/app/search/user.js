import Search from '../widgets/Search'

document.querySelectorAll('[data-search="user"]').forEach((el) => {

  const container = document.createElement('div')
  el.parentNode.insertBefore(container, el)

  const hiddenInput = document.createElement('input')
  hiddenInput.setAttribute('type', 'hidden')
  hiddenInput.setAttribute('name', el.getAttribute('name'))
  el.parentNode.insertBefore(hiddenInput, el)

  el.parentNode.removeChild(el)

  new Search(container, {
    url: window.Routing.generate('admin_users_search', { format: 'json' }),
    placeholder: el.getAttribute('placeholder'),
    onSuggestionSelected: function(suggestion) {
      hiddenInput.value = suggestion.username
    }
  })
})
