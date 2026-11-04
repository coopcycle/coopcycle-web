import { createShopBookmarks, SHOP_BOOKMARKS_KEY } from '../shop-bookmarks'

const card = (id = '1', name = 'Cooperative shop') => `<div class="restaurant-item"><a data-restaurant-path href="/en/restaurant/${id}?address=Private%20address">${name}</a><button hidden data-shop-bookmark-id="${id}" data-shop-bookmark-name="${name}" aria-pressed="false">♥</button></div>`
let root
let bookmarks
beforeEach(() => {
  localStorage.clear()
  document.body.innerHTML = `<div data-bookmark-label="Bookmark shop"><section data-shop-bookmarks hidden><ul data-shop-bookmarks-list></ul></section>${card()}</div>`
  root = document.body.firstElementChild
})
afterEach(() => bookmarks?.disconnect())

it('saves a shop locally, surfaces a link, and restores it on the next visit', () => {
  bookmarks = createShopBookmarks(root)
  const button = root.querySelector('[data-shop-bookmark-id]')
  expect(button.hidden).toBe(false)
  button.click()
  expect(button.getAttribute('aria-pressed')).toBe('true')
  expect(root.querySelector('[data-shop-bookmarks]').hidden).toBe(false)
  const savedLink = root.querySelector('[data-shop-bookmarks-list] a')
  expect(savedLink.textContent).toBe('Cooperative shop')
  expect(savedLink.getAttribute('href')).toBe('/en/restaurant/1')
  expect(localStorage.getItem(SHOP_BOOKMARKS_KEY)).not.toContain('Private')
  bookmarks.disconnect()
  bookmarks = createShopBookmarks(root)
  expect(button.getAttribute('aria-pressed')).toBe('true')
  expect(root.querySelector('[data-shop-bookmarks-list] a').textContent).toBe('Cooperative shop')
})

it('removes a saved shop from the saved list and updates every matching card', () => {
  root.insertAdjacentHTML('beforeend', card())
  bookmarks = createShopBookmarks(root)
  root.querySelector('.restaurant-item button').click()
  expect([...root.querySelectorAll('[data-shop-bookmark-id]')].every(button => button.getAttribute('aria-pressed') === 'true')).toBe(true)
  root.querySelector('[data-shop-bookmarks-list] button').click()
  expect(root.querySelector('[data-shop-bookmarks]').hidden).toBe(true)
  expect(root.querySelectorAll('.restaurant-item button[aria-pressed="false"]').length).toBe(2)
  expect(JSON.parse(localStorage.getItem(SHOP_BOOKMARKS_KEY))).toEqual([])
})

it('restores button state on cards appended by live search', () => {
  bookmarks = createShopBookmarks(root)
  root.querySelector('.restaurant-item button').click()
  root.insertAdjacentHTML('beforeend', card())
  bookmarks.refresh()
  expect(root.lastElementChild.querySelector('button').getAttribute('aria-pressed')).toBe('true')
})

it.each(['invalid JSON', '{}', '[null]', '[{"id":"1","name":"Unsafe","url":"javascript:alert(1)"}]'])('ignores invalid saved data: %s', saved => {
  localStorage.setItem(SHOP_BOOKMARKS_KEY, saved)
  bookmarks = createShopBookmarks(root)
  expect(root.querySelector('[data-shop-bookmarks]').hidden).toBe(true)
})

it('keeps current-visit bookmarks usable if browser storage rejects writes', () => {
  const setItem = jest.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('Blocked') })
  try {
    bookmarks = createShopBookmarks(root)
    root.querySelector('.restaurant-item button').click()
    expect(root.querySelector('[data-shop-bookmarks]').hidden).toBe(false)
  } finally { setItem.mockRestore() }
})

it('synchronizes bookmark changes from another tab', () => {
  bookmarks = createShopBookmarks(root)
  localStorage.setItem(SHOP_BOOKMARKS_KEY, JSON.stringify([{ id: '1', name: 'Saved elsewhere', url: '/en/restaurant/1' }]))
  window.dispatchEvent(new StorageEvent('storage', { key: SHOP_BOOKMARKS_KEY }))
  expect(root.querySelector('.restaurant-item button').getAttribute('aria-pressed')).toBe('true')
  expect(root.querySelector('[data-shop-bookmarks-list] a').textContent).toBe('Saved elsewhere')
})
