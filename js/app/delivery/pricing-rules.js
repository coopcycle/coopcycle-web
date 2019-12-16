import dragula from 'dragula'

import '../../../assets/css/dragula.scss'

const ruleSet = $('#rule-set'),
  warning = $('form[name="pricing_rule_set"] .alert-warning')

dragula([document.querySelector('.delivery-pricing-ruleset')], {
  moves: (el, container, handle) => {
    return handle.classList.contains('delivery-pricing-ruleset__rule__handle')
      || handle.parentNode.classList.contains('delivery-pricing-ruleset__rule__handle')
  }
})
  .on('dragend', () => onListChange())

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
  e.preventDefault()

  let newRule = ruleSet.attr('data-prototype')
  newRule = newRule.replace(/__name__/g, ruleSet.find('li').length)

  let newLi = $('<li></li>').addClass('delivery-pricing-ruleset__rule').html(newRule),
    $ruleExpression = newLi.find('.delivery-pricing-ruleset__rule__expression'),
    $input = $ruleExpression.find('input')

  function onExpressionChange(newExpression) {
    $input.val(newExpression)
  }

  window.CoopCycle.RulePicker(newLi.find('.rule-expression-container')[0], {onExpressionChange: onExpressionChange, zones: window.AppData.zones})
  newLi.appendTo(ruleSet)

  onListChange()
})

$(document).on('click', '.delivery-pricing-ruleset__rule__remove > a', function(e) {
  e.preventDefault()
  $(e.target).closest('li').remove()

  onListChange()
})

$('.delivery-pricing-ruleset__rule__expression').each(function(index, item) {
  let $input = $(item).find('input')
  function onExpressionChange(newExpression) {
    $input.val(newExpression)
  }
  window.CoopCycle.RulePicker($(item).find('.rule-expression-container')[0], {'expression': $input.val(), onExpressionChange: onExpressionChange, zones: window.AppData.zones})
})
