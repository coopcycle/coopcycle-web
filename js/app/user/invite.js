import Search from '../widgets/Search'

$(document).on('click', '.remove-restaurant', function(e) {
  e.preventDefault()
  $(this).closest('tr').remove()
})

document.querySelectorAll('[data-search-url]').forEach(el => {

  const $target = $(el.dataset.target)

  new Search(el, {
    url: el.dataset.searchUrl,
    placeholder: el.dataset.placeholder,
    onSuggestionSelected: function(restaurant) {
      var newRestaurant = $target.attr('data-prototype')
      newRestaurant = newRestaurant.replace(/__name__/g, $target.find('tbody > tr').length)
      newRestaurant = newRestaurant.replace(/__value__/g, restaurant.id)
      newRestaurant = newRestaurant.replace(/__label__/g, restaurant.name)
      $target.find('tbody').append($(newRestaurant))
    }
  })
})
