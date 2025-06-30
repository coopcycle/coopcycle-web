import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import Sortable from 'sortablejs'
import { Provider } from 'react-redux'

import './pricing-rules.scss'

import {
  parsePriceAST,
  PriceRange,
  FixedPrice,
  PricePerPackage,
  PercentagePrice,
} from './pricing-rule-parser'
import PricingRuleTarget from './components/PricingRuleTarget'
import AddRulePerDelivery from './components/AddRulePerDelivery'
import RulePicker from './components/RulePicker'
import PriceRangeEditor from './components/PriceRangeEditor'
import PricePerPackageEditor from './components/PricePerPackageEditor'
import LegacyPricingRulesWarning from './components/LegacyPricingRulesWarning'
import AddRulePerTask from './components/AddRulePerTask'
import { PriceChoice } from './components/PriceChoice'
import PercentageEditor from './components/PercentageEditor'
import Position from './components/Position'
import { accountSlice } from '../../entities/account/reduxSlice'
import { createStoreFromPreloadedState } from './redux/store'
import { pricingSlice } from './redux/pricingSlice'
import { AntdConfigProvider } from '../../utils/antd'

const ruleSet = $('#rule-set'),
  warning = $('form[name="pricing_rule_set"] .alert-warning')

const wrapper = document.getElementById('rule-set')

const zones = JSON.parse(wrapper.dataset.zones)
const packages = JSON.parse(wrapper.dataset.packages)

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
    [pricingSlice.name]: {
      ...pricingSlice.getInitialState(),
      zones: zones,
      packages: packages,
    }
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

const RenderRoot = ({children}) => {
  return (
    <StrictMode>
      <Provider store={store}>
        <AntdConfigProvider>
          {children}
        </AntdConfigProvider>
      </Provider>
    </StrictMode>
  )
}

const onListChange = () => {
  if ($('.delivery-pricing-ruleset > li').length === 0) {
    warning.removeClass('hidden')
  } else {
    warning.addClass('hidden')
  }

  $('.delivery-pricing-ruleset > li').each((index, el) => {
    $(el).find('.delivery-pricing-ruleset__rule__position').val(index)

    const rulePositionContainer = $(el).find('.delivery-pricing-ruleset__rule__position__container')
    const root = createRoot(rulePositionContainer[0])
    root.render(
      <RenderRoot>
        <Position position={index} />
      </RenderRoot>
    )

    $(el).attr('data-testid', `pricing-rule-${index}`)
  })
}

new Sortable(document.querySelector('.delivery-pricing-ruleset'), {
  group: 'rules',
  handle: '.delivery-pricing-ruleset__rule__handle',
  animation: 250,
  onUpdate: onListChange,
})

function renderPriceTypeItem($input, editorRoot, priceType, defaultValue) {
  switch (priceType) {
    case 'percentage':
      $input.addClass('d-none')

      editorRoot.render(
        <RenderRoot>
          <PercentageEditor
            defaultValue={defaultValue}
            onChange={({ percentage }) => {
              $input.val(`price_percentage(${percentage})`)
            }}
          />
        </RenderRoot>,
      )

      break
    case 'range':
      $input.addClass('d-none')

      editorRoot.render(
        <RenderRoot>
          <PriceRangeEditor
            defaultValue={defaultValue}
            onChange={({ attribute, price, step, threshold }) => {
              $input.val(
                `price_range(${attribute}, ${price}, ${step}, ${threshold})`,
              )
            }}
          />
        </RenderRoot>,
      )

      break
    case 'per_package':
      $input.addClass('d-none')

      editorRoot.render(
        <RenderRoot>
          <PricePerPackageEditor
            defaultValue={defaultValue}
            onChange={({ packageName, unitPrice, offset, discountPrice }) => {
              $input.val(
                `price_per_package(packages, "${packageName}", ${unitPrice}, ${offset}, ${discountPrice})`,
              )
            }}
            packages={packages}
          />
        </RenderRoot>,
      )

      break
    case 'fixed':
    default:
      editorRoot.render(null)

      $input.removeClass('d-none')
  }
}

const renderPriceChoice = item => {
  const $label = $(item).find('.delivery-pricing-ruleset__rule__price__label')
  const $input = $(item).find('.delivery-pricing-ruleset__rule__price__input')

  const priceAST = $(item).data('priceExpression')
  const expression = $input.val()

  const price = priceAST
    ? parsePriceAST(priceAST, expression)
    : new FixedPrice(0)

  let priceType = 'fixed'

  if (price instanceof PercentagePrice) {
    priceType = 'percentage'
  } else if (price instanceof PriceRange) {
    priceType = 'range'
  } else if (price instanceof PricePerPackage) {
    priceType = 'per_package'
  }

  const $parent = $input.parent()

  // Check if parent already has a React root container
  let editorContainer = $parent.find('[data-react-root-container="true"]')
  if (editorContainer.length === 0) {
    // Create a new container if none exists
    editorContainer = $('<div data-react-root-container="true" />')
    editorContainer.appendTo($parent)
  }
  const editorRoot = createRoot(editorContainer[0])

  const priceChoiceRoot = createRoot($label[0])
  priceChoiceRoot.render(
    <RenderRoot>
      <PriceChoice
        defaultValue={priceType}
        onChange={value => {
          renderPriceTypeItem($input, editorRoot, value, price)
        }}
      />
    </RenderRoot>
  )

  renderPriceTypeItem($input, editorRoot, priceType, price)
}

function hydrate(item, { ruleTarget, expression, expressionAST }) {
  const ruleTargetContainer = $(item).find(
    '.delivery-pricing-ruleset__rule__target__container',
  )
  const ruleTargetRoot = createRoot(ruleTargetContainer[0])
  ruleTargetRoot.render(
    <RenderRoot>
      <PricingRuleTarget target={ruleTarget} />
    </RenderRoot>
  )

  let $expressionInput = $(item).find(
    '.delivery-pricing-ruleset__rule__expression input',
  )
  function onExpressionChange(newExpression) {
    $expressionInput.val(newExpression)
  }
  const rulePickerRoot = createRoot($(item).find('.rule-expression-container')[0])
  rulePickerRoot.render(
    <RenderRoot>
      <RulePicker
        ruleTarget={ruleTarget}
        expression={expression}
        expressionAST={expressionAST}
        onExpressionChange={onExpressionChange}
      />
    </RenderRoot>
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

  let targetInput = newLi.find('.delivery-pricing-ruleset__rule__target input')
  targetInput.val(ruleTarget)

  hydrate(newLi, {
    ruleTarget,
    expression: undefined,
    expressionAST: undefined,
  })

  //add at the end of the list
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

function migrateToTarget(ruleTarget) {
  $('.delivery-pricing-ruleset__rule').each(function (index, item) {
    const ruleTargetInput = $(item).find(
      '.delivery-pricing-ruleset__rule__target input',
    )

    const currentRuleTarget = ruleTargetInput.val()

    if (currentRuleTarget === 'LEGACY_TARGET_DYNAMIC') {
      ruleTargetInput.val(ruleTarget)

      const expression = $(item)
        .find('.delivery-pricing-ruleset__rule__expression input')
        .val()
      const expressionAST = $(item)
        .find('.delivery-pricing-ruleset__rule__expression')
        .data('expression')

      hydrate(item, { ruleTarget, expression, expressionAST })
    }
  })
}

function hasLegacyRules() {
  let targets = []

  $('.delivery-pricing-ruleset__rule').each(function (index, item) {
    const ruleTarget = $(item)
      .find('.delivery-pricing-ruleset__rule__target input')
      .val()
    targets.push(ruleTarget)
  })

  const legacyRuleTarget = targets.find(
    target => target === 'LEGACY_TARGET_DYNAMIC',
  )

  return legacyRuleTarget !== undefined
}

$('#pricing-rule-set-header').each(function (index, item) {
  const root = createRoot(item)
  root.render(
    <RenderRoot>
      {hasLegacyRules() && (
        <LegacyPricingRulesWarning
          migrateToTarget={ruleTarget => {
            root.unmount()
            migrateToTarget(ruleTarget)
          }}
        />
      )}
    </RenderRoot>,
  )
})

$('#pricing-rule-set-footer').each(function (index, item) {
  const root = createRoot(item)
  root.render(
    <RenderRoot>
      <div className="mb-5 d-flex justify-content-end gap-4">
        <AddRulePerTask onAddRule={addPricingRule} />
        <AddRulePerDelivery onAddRule={addPricingRule} />
      </div>
    </RenderRoot>,
  )
})

// Execute the function to set the initial state
onListChange()
