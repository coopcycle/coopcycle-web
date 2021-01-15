import React from 'react'
import { render } from 'react-dom'
import Sortable from 'sortablejs'

import RulePicker from '../components/RulePicker'

const ruleSet = $('#rule-set'),
  warning = $('form[name="pricing_rule_set"] .alert-warning')

const wrapper = document.getElementById('rule-set')

const zones = JSON.parse(wrapper.dataset.zones)
const packages = JSON.parse(wrapper.dataset.packages)

new Sortable(document.querySelector('.delivery-pricing-ruleset'), {
  group: 'rules',
  handle: '.delivery-pricing-ruleset__rule__handle',
  animation: 250,
  onUpdate: onListChange,
})

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

  render(
    <RulePicker
      zones={ zones }
      packages={ packages }
      onExpressionChange={ onExpressionChange }
    />,
    newLi.find('.rule-expression-container')[0]
  )
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
  render(
    <RulePicker
      zones={ zones }
      packages={ packages }
      expression={ $input.val() }
      onExpressionChange={ onExpressionChange }
    />,
    $(item).find('.rule-expression-container')[0]
  )
})
