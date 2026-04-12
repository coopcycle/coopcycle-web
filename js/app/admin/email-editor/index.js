import grapesjs from 'grapesjs'
import mjmlPlugin from 'grapesjs-mjml'

import 'grapesjs/dist/css/grapes.min.css'

import './style.css'

// GrapeJS core locale messages (keyed by locale code)
import gjsEn from 'grapesjs/locale/en'
import gjsFr from 'grapesjs/locale/fr'
import gjsEs from 'grapesjs/locale/es'

// grapesjs-mjml locale messages
import mjmlEn from 'grapesjs-mjml/locale/en'
import mjmlFr from 'grapesjs-mjml/locale/fr'
import mjmlEs from 'grapesjs-mjml/locale/es'

const GJS_MESSAGES = { en: gjsEn, fr: gjsFr, es: gjsEs }
const MJML_MESSAGES = { en: mjmlEn, fr: mjmlFr, es: mjmlEs }

// ─── Slot configuration ───────────────────────────────────────────────────────
// Maps slot name → display label shown in the editor canvas and block panel.
// 'content' is the layout's placeholder for per-email content.

const SLOT_LABELS = {
  content:      'Email content',
  order_items:  'Order items',
  loopeat_info: 'Loopeat info',
}

// Variables available in the layout template (shown in the variables bar)
const LAYOUT_VARIABLES = [
  'brand_name',
  'background_color',
  'content_background_color',
  'primary_color',
  'primary_content_color',
]

// ─── Bootstrap: read config from DOM ─────────────────────────────────────────

const root = document.getElementById('email-editor')
if (!root) throw new Error('Missing #email-editor element')

const emailTypes           = JSON.parse(root.dataset.emailTypes)        // { type: { label_by_locale, variables, is_custom_by_locale } }
const supportedLocales     = JSON.parse(root.dataset.supportedLocales)  // { en: 'English', fr: 'Français', ... }
const i18n                 = JSON.parse(root.dataset.i18n)              // server-translated UI strings
const apiBaseUrl           = root.dataset.apiUrl                         // /admin/customize/emails/__TYPE__
const layoutApiUrl         = root.dataset.layoutUrl                      // /admin/customize/emails/layout
const styleSettingsUrl     = root.dataset.styleSettingsUrl              // /admin/customize/emails/settings
// The Symfony app locale (user's own UI language), used for GrapeJS UI strings
const appLocale            = root.dataset.locale || 'en'

// ─── State ────────────────────────────────────────────────────────────────────

let currentType      = null
let currentLocale    = Object.keys(supportedLocales)[0]
let editor           = null
let isSaving         = false
let styleView        = false    // true when the Global Style panel is shown
let isEditingLayout  = false    // true when editing the shared layout
let originalMjmlHead = ''       // preserved from the loaded template; GrapeJS breaks mj-head on parse

// Track custom status per locale per type (initialised from server data)
// Shape: { [type]: { [locale]: boolean } }
const customStatus = {}
for (const [type, meta] of Object.entries(emailTypes)) {
  customStatus[type] = { ...meta.is_custom_by_locale }
}

const layoutIsCustomByLocale = JSON.parse(root.dataset.layoutIsCustom || '{}')

// ─── DOM helpers ──────────────────────────────────────────────────────────────

function buildLocaleTabs() {
  const container = document.getElementById('ee-locale-tabs')
  for (const [locale, label] of Object.entries(supportedLocales)) {
    const btn = document.createElement('button')
    btn.type = 'button'
    btn.className = 'ee-locale-btn'
    btn.dataset.locale = locale
    btn.textContent = label
    btn.addEventListener('click', () => switchLocale(locale))
    container.appendChild(btn)
  }
  setActiveLocaleTab(currentLocale)
}

function setActiveLocaleTab(locale) {
  document.querySelectorAll('.ee-locale-btn').forEach(btn => {
    btn.classList.toggle('is-active', btn.dataset.locale === locale)
  })
}

function buildSidebar() {
  const sidebar = document.getElementById('ee-sidebar')

  // Global Style button — always at the top
  const styleBtn = document.createElement('button')
  styleBtn.type = 'button'
  styleBtn.id = 'ee-global-style-btn'
  styleBtn.className = 'ee-email-btn ee-global-style-btn'
  styleBtn.innerHTML = `
    <span class="ee-email-label">${i18n.global_style}</span>
    <span class="ee-style-icon">&#9881;</span>
  `
  styleBtn.addEventListener('click', () => showStylePanel())
  sidebar.appendChild(styleBtn)

  // Layout button — below Global Style, above email types
  const layoutBtn = document.createElement('button')
  layoutBtn.type = 'button'
  layoutBtn.id = 'ee-layout-btn'
  layoutBtn.className = 'ee-email-btn ee-layout-btn'
  layoutBtn.innerHTML = `
    <span class="ee-email-label">${i18n.layout}</span>
    <span class="ee-badge" style="display:none">custom</span>
  `
  layoutBtn.addEventListener('click', () => selectLayout())
  sidebar.appendChild(layoutBtn)

  // Divider
  const divider = document.createElement('div')
  divider.className = 'ee-sidebar-divider'
  sidebar.appendChild(divider)

  for (const [type, meta] of Object.entries(emailTypes)) {
    const btn = document.createElement('button')
    btn.type = 'button'
    btn.className = 'ee-email-btn'
    btn.dataset.type = type
    btn.innerHTML = `
      <span class="ee-email-label"></span>
      <span class="ee-badge" style="display:none">custom</span>
    `
    btn.addEventListener('click', () => selectEmail(type))
    sidebar.appendChild(btn)
  }
  refreshSidebar()
}

function refreshSidebar() {
  for (const [type] of Object.entries(emailTypes)) {
    const btn = document.querySelector(`.ee-email-btn[data-type="${type}"]`)
    if (!btn) continue
    btn.querySelector('.ee-email-label').textContent =
      emailTypes[type].label_by_locale[currentLocale] ?? type
    const badge = btn.querySelector('.ee-badge')
    badge.style.display = customStatus[type]?.[currentLocale] ? '' : 'none'
  }
  // Refresh layout badge
  const layoutBadge = document.querySelector('#ee-layout-btn .ee-badge')
  if (layoutBadge) {
    layoutBadge.style.display = layoutIsCustomByLocale[currentLocale] ? '' : 'none'
  }
}

function setActiveEmailBtn(type) {
  document.querySelectorAll('.ee-email-btn[data-type]').forEach(btn => {
    btn.classList.toggle('is-active', btn.dataset.type === type)
  })
  if (type !== null) {
    document.getElementById('ee-layout-btn')?.classList.remove('is-active')
    document.getElementById('ee-global-style-btn')?.classList.remove('is-active')
  }
}

function updateCustomBadge(type, locale, isCustom) {
  customStatus[type] = customStatus[type] ?? {}
  customStatus[type][locale] = isCustom
  const btn = document.querySelector(`.ee-email-btn[data-type="${type}"]`)
  if (!btn) return
  const badge = btn.querySelector('.ee-badge')
  if (badge) badge.style.display = (locale === currentLocale && isCustom) ? '' : 'none'
}

function updateLayoutBadge(locale, isCustom) {
  layoutIsCustomByLocale[locale] = isCustom
  const badge = document.querySelector('#ee-layout-btn .ee-badge')
  if (badge) badge.style.display = (locale === currentLocale && isCustom) ? '' : 'none'
}

function updateVariablesPanel(type, overrideVars = null) {
  const panel = document.getElementById('ee-variables')
  const vars = overrideVars ?? (emailTypes[type]?.variables ?? [])
  panel.innerHTML = vars.length
    ? vars.map(v => `<code class="ee-var" title="Click to copy">{{${v}}}</code>`).join('')
    : '<em>—</em>'

  panel.querySelectorAll('.ee-var').forEach(el => {
    el.addEventListener('click', () => {
      navigator.clipboard?.writeText(el.textContent).catch(() => {})
    })
  })
}

function applyI18n() {
  document.getElementById('ee-save-label').textContent   = i18n.save
  document.getElementById('ee-reset-label').textContent  = i18n.reset
  document.getElementById('ee-variables-label').textContent = i18n.variables + ':'
}

function setStatus(msg, type = 'info') {
  const el = document.getElementById('ee-status')
  el.textContent = msg
  el.className = 'ee-status ee-status--' + type
}

// ─── Global Style panel ───────────────────────────────────────────────────────

function buildStylePanel() {
  const panel = document.createElement('div')
  panel.id = 'ee-style-panel'
  panel.className = 'ee-style-panel'
  panel.style.display = 'none'
  panel.innerHTML = `
    <div class="ee-style-panel-inner">
      <h4 class="ee-style-panel-title">${i18n.global_style}</h4>
      <div class="ee-style-field">
        <label for="ee-color-primary">${i18n.primary_color}</label>
        <input type="color" id="ee-color-primary" value="#10ac84">
      </div>
      <div class="ee-style-field">
        <label for="ee-color-primary-content">${i18n.primary_content_color}</label>
        <input type="color" id="ee-color-primary-content" value="#ffffff">
      </div>
      <div class="ee-style-field">
        <label for="ee-color-bg">${i18n.background_color}</label>
        <input type="color" id="ee-color-bg" value="#eeeeee">
      </div>
      <div class="ee-style-field">
        <label for="ee-color-content-bg">${i18n.content_background_color}</label>
        <input type="color" id="ee-color-content-bg" value="#ffffff">
      </div>
      <div class="ee-style-actions">
        <button id="ee-save-style" class="btn btn-success btn-sm" type="button">
          ${i18n.save_style}
        </button>
        <span id="ee-style-status" class="ee-status ee-status--info"></span>
      </div>
    </div>
  `
  document.getElementById('ee-canvas').insertAdjacentElement('afterend', panel)
}

async function loadStyleSettings() {
  try {
    const res = await fetch(styleSettingsUrl, { headers: { Accept: 'application/json' } })
    if (!res.ok) return
    const data = await res.json()
    if (data['primary'])           document.getElementById('ee-color-primary').value         = data['primary']
    if (data['primary-content'])   document.getElementById('ee-color-primary-content').value  = data['primary-content']
    if (data['secondary'])         document.getElementById('ee-color-bg').value               = data['secondary']
    if (data['secondary-content']) document.getElementById('ee-color-content-bg').value       = data['secondary-content']
  } catch (_) { /* silent */ }
}

async function handleSaveStyle() {
  const btn = document.getElementById('ee-save-style')
  const statusEl = document.getElementById('ee-style-status')
  btn.disabled = true
  statusEl.textContent = ''
  statusEl.className = 'ee-status ee-status--info'

  try {
    const body = {
      'primary':           document.getElementById('ee-color-primary').value,
      'primary-content':   document.getElementById('ee-color-primary-content').value,
      'secondary':         document.getElementById('ee-color-bg').value,
      'secondary-content': document.getElementById('ee-color-content-bg').value,
    }
    const res = await fetch(styleSettingsUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    statusEl.textContent = i18n.style_saved
    statusEl.className = 'ee-status ee-status--success'
  } catch (err) {
    statusEl.textContent = 'Error: ' + err.message
    statusEl.className = 'ee-status ee-status--error'
  } finally {
    btn.disabled = false
  }
}

function showStylePanel() {
  styleView = true
  isEditingLayout = false
  currentType = null

  document.getElementById('ee-canvas').style.display = 'none'
  document.getElementById('ee-style-panel').style.display = ''

  // Toolbar: hide save/reset, clear status
  document.getElementById('ee-save').style.display  = 'none'
  document.getElementById('ee-reset').style.display = 'none'
  document.getElementById('ee-variables-bar') && (document.getElementById('ee-variables-bar').style.display = 'none')
  setStatus('')

  // Highlight the global style button, deactivate others
  document.querySelectorAll('.ee-email-btn').forEach(b => b.classList.remove('is-active'))
  document.getElementById('ee-global-style-btn').classList.add('is-active')

  // Destroy any open GrapeJS instance
  if (editor) {
    editor.destroy()
    editor = null
  }

  loadStyleSettings()
}

function hideStylePanel() {
  styleView = false
  document.getElementById('ee-canvas').style.display = ''
  document.getElementById('ee-style-panel').style.display = 'none'
  document.getElementById('ee-save').style.display  = ''
  document.getElementById('ee-reset').style.display = ''
  const varsBar = document.getElementById('ee-variables-bar')
  if (varsBar) varsBar.style.display = ''
}

// ─── API ──────────────────────────────────────────────────────────────────────

function templateApiUrl(type, locale) {
  return apiBaseUrl.replace('__TYPE__', type) + '?locale=' + locale
}

function layoutUrl(locale) {
  return layoutApiUrl + '?locale=' + locale
}

async function loadTemplate(type, locale) {
  const res = await fetch(templateApiUrl(type, locale), { headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

async function saveTemplate(type, locale, mjml) {
  const res = await fetch(templateApiUrl(type, locale), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mjml }),
  })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

async function resetTemplate(type, locale) {
  const res = await fetch(templateApiUrl(type, locale), { method: 'DELETE' })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

async function loadLayout(locale) {
  const res = await fetch(layoutUrl(locale), { headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

async function saveLayout(locale, mjml) {
  const res = await fetch(layoutUrl(locale), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mjml }),
  })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

async function resetLayout(locale) {
  const res = await fetch(layoutUrl(locale), { method: 'DELETE' })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

// ─── MJML head/body helpers ───────────────────────────────────────────────────
// The browser HTML parser does not treat custom elements (mj-font, mj-all, etc.)
// as void, so GrapeJS nests mj-head children incorrectly on parse and serializes
// a broken head. We preserve the original head string and splice it back on save.

function extractMjmlSection(mjml, tag) {
  const match = mjml.match(new RegExp(`<${tag}[\\s\\S]*?<\\/${tag}>`, 'i'))
  return match ? match[0] : ''
}

/**
 * Extracts the children of <mj-body> — just the inner sections, no wrapper.
 * Used when saving a fragment so only the content is stored.
 */
function extractBodyContent(mjml) {
  const match = mjml.match(/<mj-body[^>]*>([\s\S]*?)<\/mj-body>/i)
  return match ? match[1].trim() : ''
}

// ─── Slot components plugin ───────────────────────────────────────────────────

/**
 * Returns a GrapeJS plugin that registers a component type and block for each
 * slot name in the given array. Must be added AFTER mjmlPlugin so its
 * isComponent() check takes priority over the built-in mj-raw type.
 */
function createSlotsPlugin(slotNames) {
  return (editor) => {
    for (const slotName of slotNames) {
      const label = SLOT_LABELS[slotName] ?? slotName

      editor.Components.addType(`slot-${slotName}`, {
        isComponent: (el) =>
          el.tagName === 'MJ-RAW' && el.getAttribute('data-slot') === slotName,

        model: {
          defaults: {
            name: label,
            tagName: 'mj-raw',
            void: false,
            droppable: false,
            editable: false,
            copyable: true,
            removable: true,
            attributes: { 'data-slot': slotName },
            components: [],
          },
          toHTML() {
            return `<mj-raw data-slot="${slotName}"></mj-raw>`
          },
        },

        view: {
          onRender({ el }) {
            el.innerHTML = `<div style="
              padding: 10px 14px;
              background: #eff6ff;
              border: 2px dashed #60a5fa;
              border-radius: 4px;
              color: #1d4ed8;
              font-size: 12px;
              font-family: sans-serif;
              text-align: center;
              pointer-events: none;
            ">&#x1F4E6; ${label}</div>`
          },
        },
      })

      editor.Blocks.add(`slot-${slotName}`, {
        label,
        category: 'Dynamic',
        content: { type: `slot-${slotName}` },
        media: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2M8 7V5a2 2 0 0 0-4 0v2"/>
        </svg>`,
      })
    }
  }
}

// ─── Editor lifecycle ─────────────────────────────────────────────────────────

function initEditor(mjml, isLayout = false) {
  if (editor) {
    editor.destroy()
    editor = null
  }

  // Capture the head before GrapeJS gets a chance to corrupt it
  originalMjmlHead = extractMjmlSection(mjml, 'mj-head')

  document.getElementById('ee-canvas').innerHTML = ''

  const locale = appLocale in GJS_MESSAGES ? appLocale : 'en'

  // Layout gets the 'content' slot; per-email fragments get their own slots
  const activeSlots = isLayout ? ['content'] : (emailTypes[currentType]?.slots ?? [])
  const slotsPlugin = createSlotsPlugin(activeSlots)

  editor = grapesjs.init({
    container: '#ee-canvas',
    height: '100%',
    plugins: [mjmlPlugin, slotsPlugin],
    pluginsOpts: {
      [mjmlPlugin]: {
        resetStyleManager: true,
      },
    },
    i18n: {
      locale,
      detectLocale: false,
      localeFallback: 'en',
      messages: {
        en: { ...GJS_MESSAGES.en, ...(MJML_MESSAGES.en ?? {}) },
        [locale]: { ...GJS_MESSAGES[locale], ...(MJML_MESSAGES[locale] ?? {}) },
      },
    },
    storageManager: false,
    components: mjml,
  })
}

// ─── Interaction handlers ─────────────────────────────────────────────────────

async function selectLayout() {
  if (styleView) hideStylePanel()
  isEditingLayout = true
  currentType = null

  document.querySelectorAll('.ee-email-btn').forEach(b => b.classList.remove('is-active'))
  document.getElementById('ee-layout-btn').classList.add('is-active')

  updateVariablesPanel(null, LAYOUT_VARIABLES)
  setStatus('Loading…')
  document.getElementById('ee-save').disabled  = true
  document.getElementById('ee-reset').disabled = true

  try {
    const { mjml, is_custom } = await loadLayout(currentLocale)
    initEditor(mjml, true)
    updateLayoutBadge(currentLocale, is_custom)
    setStatus(is_custom ? i18n.status_custom : i18n.status_default, 'info')
    document.getElementById('ee-save').disabled  = false
    document.getElementById('ee-reset').disabled = !is_custom
  } catch (err) {
    setStatus('Failed to load layout: ' + err.message, 'error')
  }
}

async function selectEmail(type) {
  if (styleView) hideStylePanel()
  isEditingLayout = false

  currentType = type
  setActiveEmailBtn(type)
  updateVariablesPanel(type)
  setStatus('Loading…')
  document.getElementById('ee-save').disabled  = true
  document.getElementById('ee-reset').disabled = true

  try {
    const { mjml, is_custom } = await loadTemplate(type, currentLocale)
    initEditor(mjml, false)
    updateCustomBadge(type, currentLocale, is_custom)
    setStatus(is_custom ? i18n.status_custom : i18n.status_default, 'info')
    document.getElementById('ee-save').disabled  = false
    document.getElementById('ee-reset').disabled = !is_custom
  } catch (err) {
    setStatus('Failed to load template: ' + err.message, 'error')
  }
}

async function switchLocale(locale) {
  if (locale === currentLocale) return
  currentLocale = locale
  setActiveLocaleTab(locale)
  refreshSidebar()

  if (isEditingLayout) {
    await selectLayout()
  } else if (currentType) {
    const savedType = currentType
    currentType = null
    await selectEmail(savedType)
  }
}

async function handleSave() {
  if (!editor || isSaving) return
  if (!isEditingLayout && !currentType) return

  isSaving = true
  document.getElementById('ee-save').disabled = true
  setStatus('Saving…')

  try {
    const editorOutput = editor.getHtml()

    if (isEditingLayout) {
      // Layout: save the full MJML (preserve head, use editor body)
      const body = extractMjmlSection(editorOutput, 'mj-body')
      const mjml = `<mjml>\n  ${originalMjmlHead}\n  ${body}\n</mjml>`
      await saveLayout(currentLocale, mjml)
      updateLayoutBadge(currentLocale, true)
      document.getElementById('ee-reset').disabled = false
      setStatus(i18n.status_custom, 'success')
    } else {
      // Fragment: save only the body children (inner mj-section blocks)
      const fragment = extractBodyContent(editorOutput)
      await saveTemplate(currentType, currentLocale, fragment)
      updateCustomBadge(currentType, currentLocale, true)
      document.getElementById('ee-reset').disabled = false
      setStatus(i18n.status_custom, 'success')
    }
  } catch (err) {
    setStatus('Save failed: ' + err.message, 'error')
  } finally {
    isSaving = false
    document.getElementById('ee-save').disabled = false
  }
}

async function handleReset() {
  if (!editor) return
  if (!isEditingLayout && !currentType) return
  if (!window.confirm('Reset to the default template? Your customisation for this language will be deleted.')) return

  document.getElementById('ee-reset').disabled = true
  setStatus('Resetting…')

  try {
    if (isEditingLayout) {
      const { mjml } = await resetLayout(currentLocale)
      updateLayoutBadge(currentLocale, false)
      initEditor(mjml, true)
      setStatus(i18n.status_default, 'info')
    } else {
      const { mjml } = await resetTemplate(currentType, currentLocale)
      updateCustomBadge(currentType, currentLocale, false)
      initEditor(mjml, false)
      setStatus(i18n.status_default, 'info')
    }
  } catch (err) {
    setStatus('Reset failed: ' + err.message, 'error')
    document.getElementById('ee-reset').disabled = false
  }
}

// ─── Init ─────────────────────────────────────────────────────────────────────

applyI18n()
buildLocaleTabs()
buildSidebar()
buildStylePanel()

document.getElementById('ee-save').addEventListener('click', handleSave)
document.getElementById('ee-reset').addEventListener('click', handleReset)
document.getElementById('ee-save-style').addEventListener('click', handleSaveStyle)

// Auto-select the layout on first load
selectLayout()
