import React from 'react'
import { render } from 'react-dom'
import Sortable from 'sortablejs'
import { I18nextProvider, useTranslation } from 'react-i18next'
import classNames from 'classnames'

import RulePicker from '../components/RulePicker'
import PriceRangeEditor from '../components/PriceRangeEditor'
import './pricing-rules.scss'
import { parsePriceAST, PriceRange, FixedPrice } from './pricing-rule-parser'
import i18n from '../i18n'

const PriceChoice = ({ defaultValue, onChange }) => {

  const { t } = useTranslation()

  return (
    <select onChange={ e => onChange(e.target.value) } defaultValue={ defaultValue }>
      <option value="fixed">{ t('PRICE_RANGE_EDITOR.TYPE_FIXED') }</option>
      <option value="range">{ t('PRICE_RANGE_EDITOR.TYPE_RANGE') }</option>
    </select>
  )
}

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

const renderPriceChoice = (item) => {

  const $label = $(item).find('label')
  const $input = $(item).find('input')

  const priceAST = $(item).data('priceExpression')
  const expression = $input.val()

  const price = priceAST ? parsePriceAST(priceAST, expression) : new FixedPrice(0)

  let priceType = 'fixed'

  const rangeEditorRef = React.createRef()

  let priceRangeDefaultValue = {}

  if (price instanceof PriceRange) {
    priceType = 'range'
    $input.addClass('d-none')
    priceRangeDefaultValue = price
  }

  const $parent = $input.parent()
  const $container = $('<div />')

  $container.appendTo($parent)

  render(
    <I18nextProvider i18n={ i18n }>
      <div
        ref={ rangeEditorRef }
        className={ classNames({ 'd-none': priceType !== 'range' }) }>
        <PriceRangeEditor
          defaultValue={ priceRangeDefaultValue }
          onChange={ ({ attribute, price, step, threshold }) => {
            $input.val(`price_range(${attribute}, ${price}, ${step}, ${threshold})`)
          }} />
      </div>
    </I18nextProvider>,
    $container[0]
  )

  render(
    <I18nextProvider i18n={ i18n }>
      <PriceChoice
        defaultValue={ priceType }
        onChange={ value => {
          switch (value) {
            case 'range':
              $input.addClass('d-none')
              rangeEditorRef.current.classList.remove('d-none')
              break
            case 'fixed':
            default:
              rangeEditorRef.current.classList.add('d-none')
              $input.removeClass('d-none')
          }
        }} />
    </I18nextProvider>,
    $label[0]
  )
}

$('#add-pricing-rule').on('click', function(e) {
  e.preventDefault()

  let newRule = ruleSet.attr('data-prototype')
  newRule = newRule.replace(/__name__/g, ruleSet.find('li').length)

  let newLi = $('<li></li>').addClass('delivery-pricing-ruleset__rule').html(newRule),
    $ruleExpression = newLi.find('.delivery-pricing-ruleset__rule__expression'),
    $input = $ruleExpression.find('input[data-expression]')

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

  renderPriceChoice(
    newLi.find('.delivery-pricing-ruleset__rule__price')
  )

  onListChange()
})

$(document).on('click', '.delivery-pricing-ruleset__rule__remove > a', function(e) {
  e.preventDefault()
  $(e.target).closest('li').remove()

  onListChange()
})

$('.delivery-pricing-ruleset__rule__price').each(function(index, item) {
  renderPriceChoice(item)
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
      expressionAST={ $(item).data('expression') }
      onExpressionChange={ onExpressionChange }
    />,
    $(item).find('.rule-expression-container')[0]
  )
})
