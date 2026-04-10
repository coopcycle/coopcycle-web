import grapesjs from 'grapesjs'
import mjmlPlugin from 'grapesjs-mjml'

import 'grapesjs/dist/css/grapes.min.css'

import './style.css'

// ─── Bootstrap: read config from DOM ─────────────────────────────────────────

const root = document.getElementById('email-editor')
if (!root) throw new Error('Missing #email-editor element')

const emailTypes       = JSON.parse(root.dataset.emailTypes)       // { type: { label_by_locale, variables, is_custom_by_locale } }
const supportedLocales = JSON.parse(root.dataset.supportedLocales)  // { en: 'English', fr: 'Français', ... }
const i18n             = JSON.parse(root.dataset.i18n)             // server-translated UI strings
const apiBaseUrl       = root.dataset.apiUrl                        // /admin/customize/emails/__TYPE__

// ─── State ────────────────────────────────────────────────────────────────────

let currentType   = null
let currentLocale = Object.keys(supportedLocales)[0]
let editor        = null
let isSaving      = false

// Track custom status per locale per type (initialised from server data)
// Shape: { [type]: { [locale]: boolean } }
const customStatus = {}
for (const [type, meta] of Object.entries(emailTypes)) {
  customStatus[type] = { ...meta.is_custom_by_locale }
}

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
}

function setActiveEmailBtn(type) {
  document.querySelectorAll('.ee-email-btn').forEach(btn => {
    btn.classList.toggle('is-active', btn.dataset.type === type)
  })
}

function updateCustomBadge(type, locale, isCustom) {
  customStatus[type] = customStatus[type] ?? {}
  customStatus[type][locale] = isCustom
  const btn = document.querySelector(`.ee-email-btn[data-type="${type}"]`)
  if (!btn) return
  const badge = btn.querySelector('.ee-badge')
  if (badge) badge.style.display = (locale === currentLocale && isCustom) ? '' : 'none'
}

function updateVariablesPanel(type) {
  const panel = document.getElementById('ee-variables')
  const vars = emailTypes[type]?.variables ?? []
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

// ─── API ──────────────────────────────────────────────────────────────────────

function apiUrl(type, locale) {
  return apiBaseUrl.replace('__TYPE__', type) + '?locale=' + locale
}

async function loadTemplate(type, locale) {
  const res = await fetch(apiUrl(type, locale), { headers: { Accept: 'application/json' } })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

async function saveTemplate(type, locale, mjml) {
  const res = await fetch(apiUrl(type, locale), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mjml }),
  })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

async function resetTemplate(type, locale) {
  const res = await fetch(apiUrl(type, locale), { method: 'DELETE' })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

// ─── Editor lifecycle ─────────────────────────────────────────────────────────

function initEditor(mjml) {
  if (editor) {
    editor.destroy()
    editor = null
  }

  document.getElementById('ee-canvas').innerHTML = ''

  editor = grapesjs.init({
    container: '#ee-canvas',
    plugins: [mjmlPlugin],
    pluginsOpts: { [mjmlPlugin]: {} },
    storageManager: false,
    components: mjml,
    panels: { defaults: [] },
  })
}

// ─── Interaction handlers ─────────────────────────────────────────────────────

async function selectEmail(type) {
  if (type === currentType && !editor === false) {
    // Already loaded — only skip if the editor is actually open
    // (we always reload when the locale switches, handled in switchLocale)
  }
  currentType = type
  setActiveEmailBtn(type)
  updateVariablesPanel(type)
  setStatus('Loading…')
  document.getElementById('ee-save').disabled  = true
  document.getElementById('ee-reset').disabled = true

  try {
    const { mjml, is_custom } = await loadTemplate(type, currentLocale)
    initEditor(mjml)
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

  if (currentType) {
    // Force reload for the new locale
    const savedType = currentType
    currentType = null
    await selectEmail(savedType)
  }
}

async function handleSave() {
  if (!currentType || !editor || isSaving) return
  isSaving = true
  document.getElementById('ee-save').disabled = true
  setStatus('Saving…')

  try {
    const mjml = editor.getHtml()
    await saveTemplate(currentType, currentLocale, mjml)
    updateCustomBadge(currentType, currentLocale, true)
    document.getElementById('ee-reset').disabled = false
    setStatus(i18n.status_custom, 'success')
  } catch (err) {
    setStatus('Save failed: ' + err.message, 'error')
  } finally {
    isSaving = false
    document.getElementById('ee-save').disabled = false
  }
}

async function handleReset() {
  if (!currentType || !editor) return
  if (!window.confirm('Reset to the default template? Your customisation for this language will be deleted.')) return

  document.getElementById('ee-reset').disabled = true
  setStatus('Resetting…')

  try {
    await resetTemplate(currentType, currentLocale)
    updateCustomBadge(currentType, currentLocale, false)
    const { mjml } = await loadTemplate(currentType, currentLocale)
    initEditor(mjml)
    document.getElementById('ee-reset').disabled = true
    setStatus(i18n.status_default, 'info')
  } catch (err) {
    setStatus('Reset failed: ' + err.message, 'error')
    document.getElementById('ee-reset').disabled = false
  }
}

// ─── Init ─────────────────────────────────────────────────────────────────────

applyI18n()
buildLocaleTabs()
buildSidebar()

document.getElementById('ee-save').addEventListener('click', handleSave)
document.getElementById('ee-reset').addEventListener('click', handleReset)

// Auto-select first email type
const firstType = Object.keys(emailTypes)[0]
if (firstType) selectEmail(firstType)
