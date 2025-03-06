import React from 'react'
import { render } from '../utils/react'
import Sortable from 'sortablejs'
import { I18nextProvider, useTranslation } from 'react-i18next'
import classNames from 'classnames'

import RulePicker from '../components/RulePicker'
import PriceRangeEditor from '../components/PriceRangeEditor'
import PricePerPackageEditor from '../components/PricePerPackageEditor'
import './pricing-rules.scss'
import {
  parsePriceAST,
  PriceRange,
  FixedPrice,
  PricePerPackage,
} from './pricing-rule-parser'
import i18n from '../i18n'
import PricingRuleTarget from './components/PricingRuleTarget'
import PricingRuleSetActions from './components/PricingRuleSetActions'

const PriceChoice = ({ defaultValue, onChange }) => {
  const { t } = useTranslation()

  return (
    <select
      onChange={e => onChange(e.target.value)}
      defaultValue={defaultValue}>
      <option value="fixed">{t('PRICE_RANGE_EDITOR.TYPE_FIXED')}</option>
      <option value="range">{t('PRICE_RANGE_EDITOR.TYPE_RANGE')}</option>
      <option value="per_package">
        {t('PRICE_RANGE_EDITOR.TYPE_PER_PACKAGE')}
      </option>
    </select>
  )
}

const ruleSet = $('#rule-set'),
  warning = $('form[name="pricing_rule_set"] .alert-warning')

const wrapper = document.getElementById('rule-set')

const zones = JSON.parse(wrapper.dataset.zones)
const packages = JSON.parse(wrapper.dataset.packages)

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

new Sortable(document.querySelector('.delivery-pricing-ruleset'), {
  group: 'rules',
  handle: '.delivery-pricing-ruleset__rule__handle',
  animation: 250,
  onUpdate: onListChange,
})

const renderPriceChoice = item => {
  const $label = $(item).find('label')
  const $input = $(item).find('input')

  const priceAST = $(item).data('priceExpression')
  const expression = $input.val()

  const price = priceAST
    ? parsePriceAST(priceAST, expression)
    : new FixedPrice(0)

  let priceType = 'fixed'

  const rangeEditorRef = React.createRef()
  const pricePerPackageEditorRef = React.createRef()

  let priceRangeDefaultValue = {}
  let pricePerPackageDefaultValue = {}

  if (price instanceof PriceRange) {
    priceType = 'range'
    $input.addClass('d-none')
    priceRangeDefaultValue = price
  }

  if (price instanceof PricePerPackage) {
    priceType = 'per_package'
    $input.addClass('d-none')
    pricePerPackageDefaultValue = price
  }

  const $parent = $input.parent()

  const $priceRangeEditorContainer = $('<div />')
  const $pricePerPackageEditorContainer = $('<div />')

  $priceRangeEditorContainer.appendTo($parent)
  $pricePerPackageEditorContainer.appendTo($parent)

  render(
    <I18nextProvider i18n={i18n}>
      <div
        ref={rangeEditorRef}
        className={classNames({ 'd-none': priceType !== 'range' })}>
        <PriceRangeEditor
          defaultValue={priceRangeDefaultValue}
          onChange={({ attribute, price, step, threshold }) => {
            $input.val(
              `price_range(${attribute}, ${price}, ${step}, ${threshold})`,
            )
          }}
        />
      </div>
    </I18nextProvider>,
    $priceRangeEditorContainer[0],
  )

  render(
    <I18nextProvider i18n={i18n}>
      <div
        ref={pricePerPackageEditorRef}
        className={classNames({ 'd-none': priceType !== 'per_package' })}>
        <PricePerPackageEditor
          defaultValue={pricePerPackageDefaultValue}
          onChange={({ packageName, unitPrice, offset, discountPrice }) => {
            $input.val(
              `price_per_package(packages, "${packageName}", ${unitPrice}, ${offset}, ${discountPrice})`,
            )
          }}
          packages={packages}
        />
      </div>
    </I18nextProvider>,
    $pricePerPackageEditorContainer[0],
  )

  render(
    <I18nextProvider i18n={i18n}>
      <PriceChoice
        defaultValue={priceType}
        onChange={value => {
          switch (value) {
            case 'range':
              $input.addClass('d-none')
              pricePerPackageEditorRef.current.classList.add('d-none')

              rangeEditorRef.current.classList.remove('d-none')
              break
            case 'per_package':
              $input.addClass('d-none')
              rangeEditorRef.current.classList.add('d-none')

              pricePerPackageEditorRef.current.classList.remove('d-none')
              break
            case 'fixed':
            default:
              rangeEditorRef.current.classList.add('d-none')
              pricePerPackageEditorRef.current.classList.add('d-none')

              $input.removeClass('d-none')
          }
        }}
      />
    </I18nextProvider>,
    $label[0],
  )
}

function hydrate(item, { ruleTarget, expression, expressionAST }) {
  const ruleTargetContainer = $(item).find(
    '.delivery-pricing-ruleset__rule__target__container',
  )
  render(<PricingRuleTarget target={ruleTarget} />, ruleTargetContainer[0])

  let $expressionInput = $(item).find(
    '.delivery-pricing-ruleset__rule__expression input',
  )
  function onExpressionChange(newExpression) {
    $expressionInput.val(newExpression)
  }
  render(
    <RulePicker
      zones={zones}
      packages={packages}
      expression={expression}
      expressionAST={expressionAST}
      onExpressionChange={onExpressionChange}
    />,
    $(item).find('.rule-expression-container')[0],
  )

  const priceEl = $(item).find('.delivery-pricing-ruleset__rule__price')
  renderPriceChoice(priceEl)
}

function addPricingRule(ruleTarget) {
  let newRule = ruleSet.attr('data-prototype')
  newRule = newRule.replace(/__name__/g, ruleSet.find('li').length)

  let newLi = $('<li></li>')
    .addClass('delivery-pricing-ruleset__rule')
    .html(newRule)

  hydrate(newLi, {
    ruleTarget,
    expression: undefined,
    expressionAST: undefined,
  })

  newLi.appendTo(ruleSet)
  onListChange()
}

$(document).on(
  'click',
  '.delivery-pricing-ruleset__rule__remove > a',
  function (e) {
    e.preventDefault()
    $(e.target).closest('li').remove()

    onListChange()
  },
)

$('.delivery-pricing-ruleset__rule').each(function (index, item) {
  const ruleTarget = $(item)
    .find('.delivery-pricing-ruleset__rule__target input')
    .val()

  const expression = $(item)
    .find('.delivery-pricing-ruleset__rule__expression input')
    .val()
  const expressionAST = $(item)
    .find('.delivery-pricing-ruleset__rule__expression')
    .data('expression')

  hydrate(item, { ruleTarget, expression, expressionAST })
})

$('#pricing-rule-set-actions').each(function (index, item) {
  render(<PricingRuleSetActions onAddRule={addPricingRule} />, item)
})
