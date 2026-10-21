import React, { act } from 'react'
import { createRoot } from 'react-dom/client'
import { message } from 'antd'
import PricingRuleSetForm from '../PricingRuleSetForm'
import fixedPriceAst from '../../../delivery/pricing/__tests__/fixed-price.json'
import perPackagePriceAst from '../../../delivery/pricing/__tests__/price-per-package-function.json'
import percentagePriceAst from '../../../delivery/pricing/__tests__/price-percentage.json'
import rangePriceAst from '../../../delivery/pricing/__tests__/price-range-per-delivery-volume-units.json'

let mockRuleSet
const mockUpdate = jest.fn(() => ({ unwrap: () => Promise.resolve() }))
const mockPackages = [{ name: 'XXL', packageSet: '/api/package_sets/1' }]

jest.mock('../../../api/slice', () => ({
  useGetPricingRuleSetQuery: () => ({ data: mockRuleSet, isLoading: false }),
  useCreatePricingRuleSetMutation: () => [jest.fn(), { isLoading: false }],
  useUpdatePricingRuleSetMutation: () => [mockUpdate, { isLoading: false }],
  useGetPackagesQuery: () => ({ data: mockPackages, isFetching: false }),
  useGetZonesQuery: () => ({ data: [], isFetching: false }),
  useGetTimeSlotsQuery: () => ({ data: [], isFetching: false }),
}))
jest.mock('../../Applications', () => () => null)
jest.mock('../../HelpIcon', () => () => null)
jest.mock('../matrix/PricingMatrixSection', () => () => null)

const distanceRangeAst = {
  nodes: {
    attributes: { operator: 'in' },
    nodes: {
      left: { attributes: { name: 'distance' }, nodes: [] },
      right: {
        attributes: { operator: '..' },
        nodes: {
          left: { attributes: { value: 1000 }, nodes: [] },
          right: { attributes: { value: 3000 }, nodes: [] },
        },
      },
    },
  },
}

describe('duplicating pricing rules', () => {
  let root
  let container

  beforeAll(() => {
    global.IS_REACT_ACT_ENVIRONMENT = true
    // The application uses the automatic JSX runtime; Jest uses the classic runtime.
    global.React = React
    window.matchMedia = () => ({
      matches: false,
      addListener: () => {},
      removeListener: () => {},
    })
    document.body.dataset.currencySymbol = '€'
  })

  beforeEach(() => {
    jest.spyOn(message, 'success').mockImplementation(() => {})
    mockRuleSet = {
      id: 1,
      name: 'Delivery prices',
      strategy: 'map',
      options: [],
      rules: [{
        '@id': '/api/pricing_rules/10',
        id: 10,
        target: 'DELIVERY',
        position: 0,
        name: 'Short delivery',
        expression: 'distance in 1000..3000',
        expressionAst: distanceRangeAst,
        price: '1053',
        priceAst: fixedPriceAst,
      }],
    }
    container = document.createElement('div')
    document.body.appendChild(container)
    root = createRoot(container)
  })

  afterEach(() => {
    act(() => root.unmount())
    container.remove()
    jest.restoreAllMocks()
  })

  afterAll(() => {
    delete global.IS_REACT_ACT_ENVIRONMENT
    delete global.React
    delete window.matchMedia
    delete document.body.dataset.currencySymbol
  })

  async function render() {
    await act(async () => root.render(
      <PricingRuleSetForm ruleSetId={1} ruleSetUri="/api/pricing_rule_sets/1" />
    ))
  }

  function rule(index) {
    return container.querySelector(`[data-testid="pricing-rule-set-rule-${index}"]`)
  }

  async function click(element) {
    expect(element).toBeTruthy()
    await act(async () => element.dispatchEvent(new MouseEvent('click', { bubbles: true })))
  }

  async function change(input, value) {
    expect(input).not.toBeNull()
    await act(async () => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, value)
      input.dispatchEvent(new Event('input', { bubbles: true }))
    })
  }

  async function select(element, value) {
    await act(async () => {
      element.value = value
      element.dispatchEvent(new Event('change', { bubbles: true }))
    })
  }

  async function press(element, keyCode) {
    await act(async () => element.dispatchEvent(new KeyboardEvent('keydown', { keyCode, bubbles: true })))
  }

  async function save() {
    await act(async () => {
      container.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }))
    })
    expect(mockUpdate).toHaveBeenCalledTimes(1)
    return mockUpdate.mock.calls[0][0].rules
  }

  async function selectPriceType(index, type) {
    const selector = rule(index).querySelector('[data-testid="rule-price-type"] .ant-select-selector')
    await act(async () => selector.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })))
    await click(Array.from(document.querySelectorAll('.ant-select-item-option')).find(option => option.textContent === type))
  }

  it('copies unsaved name, conditions and price with a new identity beside the source', async () => {
    mockRuleSet.rules.push({
      ...mockRuleSet.rules[0], '@id': '/api/pricing_rules/11', id: 11,
      name: 'Following rule', position: 1,
    })
    await render()
    await change(rule(0).querySelector('[data-testid="rule-name"]'), 'Edited delivery')
    await change(rule(0).querySelector('[data-testid="condition-0"] input'), '2')
    await change(rule(0).querySelector('[data-testid="rule-fixed-price-input"]'), '12.50')
    await click(rule(0).querySelector('[data-testid="rule-duplicate"]'))

    expect(rule(1).querySelector('[data-testid="rule-name"]').value).toBe('Edited delivery')
    expect(rule(1).querySelector('[data-testid="condition-0"] input').value).toBe('2')
    expect(rule(1).querySelector('[data-testid="rule-fixed-price-input"]').value).toBe('12.50')
    expect(mockUpdate).not.toHaveBeenCalled()

    await change(rule(1).querySelector('[data-testid="rule-name"]'), 'Copy only')
    await change(rule(1).querySelectorAll('[data-testid="condition-0"] input')[1], '4')
    await change(rule(1).querySelector('[data-testid="rule-fixed-price-input"]'), '15.00')

    expect(rule(0).querySelector('[data-testid="rule-name"]').value).toBe('Edited delivery')
    expect(rule(0).querySelectorAll('[data-testid="condition-0"] input')[1].value).toBe('3')
    expect(rule(0).querySelector('[data-testid="rule-fixed-price-input"]').value).toBe('12.50')

    await change(rule(0).querySelector('[data-testid="condition-0"] input'), '1.5')
    expect(rule(1).querySelector('[data-testid="condition-0"] input').value).toBe('2')
    expect(rule(2).querySelector('[data-testid="rule-name"]').value).toBe('Following rule')

    const rules = await save()
    expect(rules).toHaveLength(3)
    expect(rules[0]).toMatchObject({
      '@id': '/api/pricing_rules/10', id: 10, position: 0,
      name: 'Edited delivery', expression: 'distance in 1500..3000', price: '1250',
    })
    expect(rules[1]).toMatchObject({
      target: 'DELIVERY', position: 1,
      name: 'Copy only', expression: 'distance in 2000..4000', price: '1500',
    })
    expect(rules[1]).not.toHaveProperty('@id')
    expect(rules[1]).not.toHaveProperty('id')
    expect(rules[1]).not.toHaveProperty('editorState')
    expect(rules[2]).toMatchObject({ '@id': '/api/pricing_rules/11', id: 11, position: 2 })
  })

  it('preserves edited per-package prices and discounts when duplicating', async () => {
    mockRuleSet.rules[0].price = 'price_per_package(packages, "XXL", 1240, 3, 210)'
    mockRuleSet.rules[0].priceAst = perPackagePriceAst
    await render()
    const editor = rule(0).querySelector('[data-testid="price_rule_price_per_package_editor"]')
    await change(editor.querySelector('input'), '2.50')
    await click(rule(0).querySelector('[data-testid="rule-duplicate"]'))

    const copyEditor = rule(1).querySelector('[data-testid="price_rule_price_per_package_editor"]')
    expect(copyEditor).not.toBeNull()
    expect(Array.from(copyEditor.querySelectorAll('input')).map(input => input.value))
      .toEqual(Array.from(editor.querySelectorAll('input')).map(input => input.value))

    const rules = await save()
    expect(rules[1].price).toBe(rules[0].price)
    expect(rules[1].price).toBe('price_per_package(packages, "XXL", 250, 3, 210)')
  })

  it('preserves a zero percentage when copying a full discount', async () => {
    mockRuleSet.rules[0].price = 'price_percentage(8500)'
    mockRuleSet.rules[0].priceAst = percentagePriceAst
    await render()
    await change(rule(0).querySelector('[data-testid="rule-percentage-input"]'), '-100')
    await click(rule(0).querySelector('[data-testid="rule-duplicate"]'))

    expect(rule(1).querySelector('[data-testid="rule-percentage-input"]').value).toBe('-100')
    const rules = await save()
    expect(rules.map(rule => rule.price)).toEqual(['price_percentage(0)', 'price_percentage(0)'])
  })

  it('preserves edited range prices, steps, thresholds and multipliers', async () => {
    mockRuleSet.rules[0].price = 'price_range(distance, 50, 1000, 2500, delivery.packages.totalVolumeUnits())'
    mockRuleSet.rules[0].priceAst = rangePriceAst
    await render()
    await change(rule(0).querySelector('[data-testid="rule-price-range-price"]'), '1.25')
    await change(rule(0).querySelector('[data-testid="rule-price-range-step"]'), '0.5')
    await change(rule(0).querySelector('[data-testid="rule-price-range-threshold"]'), '3')
    await click(rule(0).querySelector('[data-testid="rule-duplicate"]'))

    expect(rule(1).querySelector('[data-testid="rule-price-range-price"]').value).toBe('1.25')
    expect(rule(1).querySelector('[data-testid="rule-price-range-step"]').value).toBe('0.5')
    expect(rule(1).querySelector('[data-testid="rule-price-range-threshold"]').value).toBe('3')
    expect(rule(1).querySelector('[data-testid="rule-price-range-multiplier"]').value)
      .toBe('delivery.packages.totalVolumeUnits()')
    const rules = await save()
    expect(rules.map(rule => rule.price)).toEqual([
      'price_range(distance, 125, 500, 3000, delivery.packages.totalVolumeUnits())',
      'price_range(distance, 125, 500, 3000, delivery.packages.totalVolumeUnits())',
    ])
  })

  it('copies the selected price type after changing it without saving', async () => {
    await render()
    await selectPriceType(0, 'Percentage')
    await change(rule(0).querySelector('[data-testid="rule-percentage-input"]'), '25')
    await click(rule(0).querySelector('[data-testid="rule-duplicate"]'))

    expect(rule(1).querySelector('[data-testid="rule-percentage-input"]').value).toBe('25')
    expect(rule(1).querySelector('[data-testid="rule-fixed-price-input"]')).toBeNull()
    const rules = await save()
    expect(rules.map(rule => rule.price)).toEqual(['price_percentage(12500)', 'price_percentage(12500)'])
  })

  it('copies a brand-new supplement within its own group', async () => {
    mockRuleSet.rules[0].target = 'TASK'
    await render()
    await click(container.querySelector('[data-testid="pricing-rule-set-add-supplement-target-delivery"]'))
    await change(rule(1).querySelector('[data-testid="rule-name"]'), 'Extra handling')
    await change(rule(1).querySelector('[data-testid="rule-fixed-price-input"]'), '5')
    await click(rule(1).querySelector('[data-testid="rule-duplicate"]'))
    await change(rule(2).querySelector('[data-testid="rule-name"]'), 'Second supplement')

    const rules = await save()
    expect(rules).toHaveLength(3)
    expect(rules.slice(1)).toEqual([
      { target: 'DELIVERY', expression: 'false', price: '500', name: 'Extra handling', position: 1 },
      { target: 'DELIVERY', expression: 'false', price: '500', name: 'Second supplement', position: 2 },
    ])
    expect(rules[0]).toMatchObject({ '@id': '/api/pricing_rules/10', target: 'TASK', position: 0 })
  })

  it('copies a brand-new task rule with conditions that have no saved AST', async () => {
    await render()
    await click(container.querySelector('[data-testid="pricing-rule-set-add-rule-target-task"]'))
    await click(rule(0).querySelector('[data-testid="rule-add-condition"]'))
    await select(rule(0).querySelector('[data-testid="condition-type-select"]'), 'weight')
    await select(rule(0).querySelector('[data-testid="condition-operator-select"]'), '<')
    await change(rule(0).querySelector('[data-testid="condition-number-input"]'), '2')
    await change(rule(0).querySelector('[data-testid="rule-fixed-price-input"]'), '8')
    await click(rule(0).querySelector('[data-testid="rule-duplicate"]'))

    expect(rule(1).querySelector('[data-testid="condition-type-select"]').value).toBe('weight')
    expect(rule(1).querySelector('[data-testid="condition-number-input"]').value).toBe('2')
    const rules = await save()
    expect(rules.slice(0, 2)).toEqual([
      { target: 'TASK', expression: 'weight < 2000', price: '800', name: null, position: 0 },
      { target: 'TASK', expression: 'weight < 2000', price: '800', name: null, position: 1 },
    ])
    expect(rules[2]).toMatchObject({ '@id': '/api/pricing_rules/10', target: 'DELIVERY', position: 2 })
  })

  it('can reorder a copy before its source', async () => {
    await render()
    await click(rule(0).querySelector('[data-testid="rule-duplicate"]'))
    await change(rule(1).querySelector('[data-testid="rule-name"]'), 'Moved copy')
    const handle = rule(1).querySelector('[data-rfd-drag-handle-draggable-id]')
    await press(handle, 32)
    await press(handle, 38)
    await press(handle, 32)

    const rules = await save()
    expect(rules.map(rule => rule.name)).toEqual(['Moved copy', 'Short delivery'])
    expect(rules[0]).not.toHaveProperty('@id')
    expect(rules[1]['@id']).toBe('/api/pricing_rules/10')
    expect(rules.map(rule => rule.position)).toEqual([0, 1])
  })

  it('can duplicate a new copy again and remove it without changing the source', async () => {
    await render()
    await click(rule(0).querySelector('[data-testid="rule-duplicate"]'))
    await click(rule(1).querySelector('[data-testid="rule-duplicate"]'))
    expect(rule(2)).not.toBeNull()
    await click(rule(1).querySelector('[data-testid="rule-remove"]'))

    const rules = await save()
    expect(rules).toHaveLength(2)
    expect(rules[0]['@id']).toBe('/api/pricing_rules/10')
    expect(rules[1]).not.toHaveProperty('@id')
    expect(rules[1]).not.toHaveProperty('id')
    expect(rules.map(rule => rule.position)).toEqual([0, 1])
  })
})
