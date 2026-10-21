import React, { act } from 'react'
import { createRoot } from 'react-dom/client'
import { Provider } from 'react-redux'
import { createStore } from 'redux'
import i18next from 'i18next'
import { I18nextProvider } from 'react-i18next'

import CourierSelect from '../CourierSelect'

describe('CourierSelect', () => {
  const active = { id: 1, username: 'active-courier', enabled: true }
  const former = { id: 2, username: 'former-courier', enabled: false }
  const planned = { id: 3, username: 'planned-courier', enabled: true }
  const state = {
    config: { couriersList: [active, former, planned] },
    logistics: {
      entities: {
        taskLists: {
          ids: [planned.username],
          entities: {
            [planned.username]: { username: planned.username, items: [] },
          },
        },
      },
    },
  }
  let container
  let root

  beforeAll(() => {
    global.IS_REACT_ACT_ENVIRONMENT = true
    i18next.init({ lng: 'en', resources: { en: { translation: {} } } })
  })

  beforeEach(() => {
    window.Routing = { generate: () => '/avatar.png' }
    container = document.createElement('div')
    document.body.appendChild(container)
    root = createRoot(container)
  })

  afterEach(() => {
    act(() => root.unmount())
    container.remove()
    delete window.Routing
  })

  afterAll(() => {
    delete global.IS_REACT_ACT_ENVIRONMENT
  })

  function render(props) {
    act(() => {
      root.render(
        <Provider store={createStore(() => state)}>
          <I18nextProvider i18n={i18next}>
            <CourierSelect {...props} />
          </I18nextProvider>
        </Provider>
      )
    })
  }

  function openMenu() {
    const input = container.querySelector('input')
    act(() => {
      input.focus()
      input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))
    })
  }

  function optionUsernames() {
    return Array.from(document.querySelectorAll('[data-cypress-select-username]'))
      .map(option => option.getAttribute('data-cypress-select-username'))
  }

  it.each([
    ['enabled', active],
    ['disabled', former],
  ])('shows the %s courier on a read-only assigned task', (status, courier) => {
    render({ username: courier.username, isDisabled: true })

    expect(container.textContent).toContain(courier.username)
    expect(container.querySelector('input').disabled).toBe(true)
  })

  it('shows the assigned disabled courier without offering it for a new assignment', () => {
    render({ username: former.username })
    openMenu()

    expect(container.textContent).toContain(former.username)
    expect(optionUsernames()).toEqual([active.username, planned.username])
  })

  it('can add an enabled unplanned courier without an initial selection', () => {
    const onChange = jest.fn()
    render({ isMulti: true, exclude: true, onChange })
    openMenu()

    expect(optionUsernames()).toEqual([active.username])

    const option = document.querySelector(`[data-cypress-select-username="${active.username}"]`)
    act(() => option.dispatchEvent(new MouseEvent('click', { bubbles: true })))

    expect(onChange).toHaveBeenCalledWith([
      { ...active, value: active.username, label: active.username },
    ], expect.anything())
  })
})
