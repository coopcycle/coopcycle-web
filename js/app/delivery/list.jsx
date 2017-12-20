$('#delivery-modal').on('show.bs.modal', function (event) {

  var button = $(event.relatedTarget)
  var delivery = button.data('delivery')
  var action = button.data('action')

  var $modal = $(this)

  $modal.find('[data-action]').each(function(index, el) {
    if ($(el).data('action') === action) {
      $(el).removeClass('hidden')
    } else {
      $(el).addClass('hidden')
    }
  })

  $modal.find('span[data-delivery]').text(delivery)

  const formAction = window.AppData.Delivery.actions[action]

  $modal.find('form')
    .attr('action', formAction.replace('__DELIVERY_ID__', delivery))

})
