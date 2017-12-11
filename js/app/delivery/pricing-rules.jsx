import { Sortable } from '@shopify/draggable'

const ruleSet = $('#rule-set')
const warning = $('form[name="pricing_rule_set"] .alert-warning')

const onListChange = () => {
  if ($('.delivery-pricing-ruleset > li').length === 0) {
    warning.removeClass('hidden')
  } else {
    warning.addClass('hidden')
  }

  $('.delivery-pricing-ruleset > li').each((index, el) => {
    $(el).find('.delivery-pricing-ruleset__rule__position').val(index)
  })
}

$('#add-pricing-rule').on('click', function(e) {
  e.preventDefault();

  var newRule = ruleSet.attr('data-prototype')
  newRule = newRule.replace(/__name__/g, ruleSet.find('li').length)

  var newLi = $('<li></li>').addClass('delivery-pricing-ruleset__rule').html(newRule)
  newLi.appendTo(ruleSet)

  onListChange()
})

$(document).on('click', '.delivery-pricing-ruleset__rule__remove > a', function(e) {
  e.preventDefault()
  $(e.target).closest('li').remove()

  onListChange()
})

const sortable = new Sortable(document.querySelector('.delivery-pricing-ruleset'), {
  draggable: '.delivery-pricing-ruleset__rule',
  handle:    '.delivery-pricing-ruleset__rule__handle',
})

sortable.on('mirror:destroy', () => onListChange())
