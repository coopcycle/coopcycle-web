import React, { act } from 'react'
import { createRoot } from 'react-dom/client'
import PricePerPackageEditor from '../PricePerPackageEditor'

jest.mock('../../../../api/slice', () => ({
  useGetPackagesQuery: () => ({
    data: [{ name: 'XXL', packageSet: '/api/package_sets/1' }],
    isFetching: false,
  }),
}))

describe('PricePerPackageEditor', () => {
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
  })

  beforeEach(() => {
    container = document.createElement('div')
    document.body.appendChild(container)
    root = createRoot(container)
  })

  afterEach(() => {
    act(() => root.unmount())
    container.remove()
  })

  afterAll(() => {
    delete global.IS_REACT_ACT_ENVIRONMENT
    delete global.React
    delete window.matchMedia
  })

  async function change(input, value) {
    expect(input).not.toBeNull()
    await act(async () => {
      Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, value)
      input.dispatchEvent(new Event('input', { bubbles: true }))
    })
  }

  it('keeps the unit price when the discount threshold changes', async () => {
    const onChange = jest.fn()

    await act(async () => root.render(
      <PricePerPackageEditor
        defaultValue={{ packageName: 'XXL', unitPrice: 1240, offset: 3, discountPrice: 210 }}
        onChange={onChange}
      />
    ))

    await change(container.querySelector('[data-testid="rule-per-package-offset-input"]'), '5')

    expect(onChange).toHaveBeenLastCalledWith({
      packageName: 'XXL',
      unitPrice: 1240,
      offset: 5,
      discountPrice: 210,
    })
  })
})
