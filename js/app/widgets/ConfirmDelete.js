export default function(selector) {

  $(selector).each(function() {
    const message = $(this).data('confirm-message')

    const $iconWrapper = $('<div>').addClass('icon')
    const $textWrapper = $('<div>').addClass('message')

    const $icon = $('<i>').addClass('fa fa-trash-o')
    const $span = $('<span>').text(message)

    $iconWrapper.append($icon)
    $textWrapper.append($span)

    $(this).empty()
    $(this).append($iconWrapper)
    $(this).append($textWrapper)
  })

  $(selector).on('click', function(e) {
    if(!$(this).hasClass('confirm')) {
      e.preventDefault()
      $(this).addClass('confirm')
    }
  })

  $(selector).on('mouseout', function() {
    const $el = $(this)
    if ($el.hasClass('confirm')) {
      setTimeout(() => $el.removeClass('confirm'), 3000)
    }
  })

}
