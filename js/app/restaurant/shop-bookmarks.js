export const SHOP_BOOKMARKS_KEY = 'coopcycle.shopBookmarks'

export function createShopBookmarks(root) {
  let storage
  try {
    storage = window.localStorage
  } catch {
    // Bookmarks still work for this visit when browser storage is unavailable.
  }

  const read = () => {
    try {
      const saved = JSON.parse(storage?.getItem(SHOP_BOOKMARKS_KEY) || '[]')
      if (!Array.isArray(saved)) return []
      return saved.filter(shop => {
        if (!shop || typeof shop.id !== 'string' || typeof shop.name !== 'string' || typeof shop.url !== 'string') return false
        const url = new URL(shop.url, window.location.href)
        return url.origin === window.location.origin && ['http:', 'https:'].includes(url.protocol)
      })
    } catch {
      return []
    }
  }
  let bookmarks = read()
  const section = root.querySelector('[data-shop-bookmarks]')
  const list = root.querySelector('[data-shop-bookmarks-list]')

  const refresh = () => {
    root.querySelectorAll('[data-shop-bookmark-id]').forEach(button => {
      button.hidden = false
      button.setAttribute('aria-pressed', String(bookmarks.some(shop => shop.id === button.dataset.shopBookmarkId)))
    })
    if (!section || !list) return
    list.replaceChildren()
    bookmarks.forEach(shop => {
      const item = document.createElement('li')
      const link = document.createElement('a')
      link.href = shop.url
      link.textContent = shop.name
      const button = document.createElement('button')
      button.type = 'button'
      button.dataset.shopBookmarkId = shop.id
      button.className = 'btn btn-ghost btn-sm'
      button.setAttribute('aria-pressed', 'true')
      button.setAttribute('aria-label', `${root.dataset.bookmarkLabel}: ${shop.name}`)
      button.textContent = '♥'
      item.append(link, button)
      list.append(item)
    })
    section.hidden = bookmarks.length === 0
  }

  const onClick = event => {
    const button = event.target.closest('[data-shop-bookmark-id]')
    if (!button || !root.contains(button)) return
    const id = button.dataset.shopBookmarkId
    const existing = bookmarks.find(shop => shop.id === id)
    if (existing) {
      bookmarks = bookmarks.filter(shop => shop.id !== id)
    } else {
      const card = button.closest('.restaurant-item')
      const link = card?.querySelector('[data-restaurant-path]')
      if (!link) return
      const url = new URL(link.href, window.location.href)
      if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) return
      bookmarks.push({ id, name: button.dataset.shopBookmarkName, url: url.pathname })
    }
    try {
      storage?.setItem(SHOP_BOOKMARKS_KEY, JSON.stringify(bookmarks))
    } catch {
      // Keep the current visit usable if storage is full or blocked.
    }
    refresh()
  }
  const onStorage = event => {
    if (event.key !== SHOP_BOOKMARKS_KEY && event.key !== null) return
    bookmarks = read()
    refresh()
  }
  root.addEventListener('click', onClick)
  window.addEventListener('storage', onStorage)
  refresh()

  return {
    refresh,
    disconnect() {
      root.removeEventListener('click', onClick)
      window.removeEventListener('storage', onStorage)
    },
  }
}
