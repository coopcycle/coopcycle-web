import MicroModal from 'micromodal'

// @see https://github.com/ghosh/Micromodal/blob/master/tests/assets/modal.css
import modalCSS from './modal.css'

// @see https://github.com/ghosh/Micromodal/blob/master/tests/examples/programmatic.html
import modalHTML from './modal.html'

export function embed(selector) {

  const style = document.createElement('style')
  style.setAttribute('type', 'text/css')
  style.innerHTML = modalCSS.toString()

  document.querySelector('head').appendChild(style)

  const el = document.querySelector(selector)
  el.innerHTML = modalHTML

  MicroModal.init()
  MicroModal.show('modal-1')
}

// @see https://gist.github.com/6174/6062387
const uuid = () => {
  return Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15)
}

window.addEventListener('DOMContentLoaded', () => {

  const elements =
    Array.from(document.querySelectorAll('[data-coopcycle-embed]'))

  if (elements.length > 0) {
    const style = document.createElement('style')
    style.setAttribute('type', 'text/css')
    style.innerHTML = modalCSS.toString()

    document.querySelector('head').appendChild(style)
  }

  elements.forEach(element => {

    const url = element.dataset.coopcycleEmbed
    const embedURL = url + '?embed'

    const container = document.createElement('div')
    container.innerHTML = modalHTML
    document.querySelector('body').appendChild(container)

    const modalID = `coopcycle-${uuid()}`

    container.querySelector('.coopcycle-modal').setAttribute('id', modalID)

    element.addEventListener('click', e => {

      e.preventDefault()

      MicroModal.show(modalID, {
        onShow: modal => {
          const iframe = modal.querySelector('iframe')
          // iframe.referrerPolicy = 'origin-when-cross-origin'
          if (!iframe.hasAttribute('src')) {
            iframe.setAttribute('src', embedURL)
          }
        }
      })

    })
  })
})
