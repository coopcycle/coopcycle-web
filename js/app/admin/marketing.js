import grapesjs from 'grapesjs'
import mjmlPlugin from 'grapesjs-mjml'

import 'grapesjs/dist/css/grapes.min.css'

import mountAutoSubmitSwitch from './components/auto-submit-switch'

import '../bootstrap-reset.scss'

/**
 * A campaign body is one free-form MJML document, with none of the email
 * types, per-locale variants, variables or locked slots the transactional
 * editor exists to manage. It shares the libraries with that editor rather
 * than the editor itself: bending a 700-line integration built around those
 * concepts into also meaning "just edit this one field" would put the
 * transactional templates at risk for no gain here.
 *
 * The editor reads and writes the form's hidden MJML field, so saving a
 * campaign is an ordinary form submit.
 */
function mountCampaignEditor(root) {
  const input = document.getElementById(root.dataset.input)

  if (!input) {
    return
  }

  const editable = root.dataset.editable === '1'

  const editor = grapesjs.init({
    container: root,
    fromElement: false,
    height: '640px',
    storageManager: false,
    plugins: [mjmlPlugin],
    pluginsOpts: {
      [mjmlPlugin]: {},
    },
  })

  editor.setComponents(input.value || '<mjml><mj-body></mj-body></mjml>')

  if (!editable) {
    // A campaign on its way out must not be edited: two different emails
    // would go out under one campaign, with no record of who got which.
    editor.getModel().set('readonly', true)
    root.style.pointerEvents = 'none'
    root.style.opacity = '0.6'

    return
  }

  // Written back on every change rather than only on submit, so a save
  // triggered from anywhere on the page still carries the current body.
  const sync = () => {
    try {
      input.value = editor.getHtml()
    } catch (e) {
      // Leave the last good value in place rather than clearing the body.
    }
  }

  editor.on('update', sync)
  editor.on('component:update', sync)

  const form = input.closest('form')
  if (form) {
    form.addEventListener('submit', sync)
  }
}

$(function () {
  mountAutoSubmitSwitch('#marketing_automation_active_form_active')

  const root = document.getElementById('campaign-editor')

  if (root) {
    mountCampaignEditor(root)
  }
})
