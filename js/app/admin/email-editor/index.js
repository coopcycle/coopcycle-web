import grapesjs from 'grapesjs'
import mjmlPlugin from 'grapesjs-mjml'

import 'grapesjs/dist/css/grapes.min.css'

import './style.css'

// ─── Bootstrap ────────────────────────────────────────────────────────────────

const root = document.getElementById('email-editor')
if (!root) {
  throw new Error('Missing #email-editor element')
}

const emailTypes = JSON.parse(root.dataset.emailTypes)
// e.g. /admin/customize/emails/__TYPE__  — we replace __TYPE__ at runtime
const apiBaseUrl = root.dataset.apiUrl

// ─── State ────────────────────────────────────────────────────────────────────

let currentType = null
let editor = null
let isSaving = false

// ─── DOM helpers ──────────────────────────────────────────────────────────────

function buildSidebar() {
  const sidebar = document.getElementById('ee-sidebar')
  Object.entries(emailTypes).forEach(([type, meta]) => {
    const btn = document.createElement('button')
    btn.type = 'button'
    btn.className = 'ee-email-btn'
    btn.dataset.type = type
    btn.innerHTML = `
      <span class="ee-email-label">${meta.label}</span>
      ${meta.is_custom ? '<span class="ee-badge">custom</span>' : ''}
    `
    btn.addEventListener('click', () => selectEmail(type))
    sidebar.appendChild(btn)
  })
}

function setActiveBtn(type) {
  document.querySelectorAll('.ee-email-btn').forEach(btn => {
    btn.classList.toggle('is-active', btn.dataset.type === type)
  })
}

function setBadge(type, isCustom) {
  const btn = document.querySelector(`.ee-email-btn[data-type="${type}"]`)
  if (!btn) return
  let badge = btn.querySelector('.ee-badge')
  if (isCustom && !badge) {
    badge = document.createElement('span')
    badge.className = 'ee-badge'
    badge.textContent = 'custom'
    btn.appendChild(badge)
  } else if (!isCustom && badge) {
    badge.remove()
  }
}

function updateVariablesPanel(type) {
  const panel = document.getElementById('ee-variables')
  const vars = (emailTypes[type] || {}).variables || []
  panel.innerHTML = vars.length
    ? vars.map(v => `<code class="ee-var" title="Click to copy">{{${v}}}</code>`).join('')
    : '<em>No variables</em>'

  // Click-to-copy
  panel.querySelectorAll('.ee-var').forEach(el => {
    el.addEventListener('click', () => {
      navigator.clipboard?.writeText(el.textContent)
        .catch(() => {/* silently ignore on http */})
    })
  })
}

function setStatus(msg, type = 'info') {
  const el = document.getElementById('ee-status')
  el.textContent = msg
  el.className = 'ee-status ee-status--' + type
}

// ─── API ──────────────────────────────────────────────────────────────────────

function apiUrl(type) {
  return apiBaseUrl.replace('__TYPE__', type)
}

async function loadTemplate(type) {
  const res = await fetch(apiUrl(type), {
    headers: { Accept: 'application/json' },
  })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

async function saveTemplate(type, mjml) {
  const res = await fetch(apiUrl(type), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mjml }),
  })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

async function resetTemplate(type) {
  const res = await fetch(apiUrl(type), { method: 'DELETE' })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  return res.json()
}

// ─── Editor lifecycle ──────────────────────────────────────────────────────────

function initEditor(mjml) {
  if (editor) {
    editor.destroy()
    editor = null
  }

  // Clear previous content
  const canvas = document.getElementById('ee-canvas')
  canvas.innerHTML = ''

  editor = grapesjs.init({
    container: '#ee-canvas',
    plugins: [mjmlPlugin],
    pluginsOpts: {
      [mjmlPlugin]: {},
    },
    storageManager: false,
    components: mjml,
    // Disable default panels that are not needed
    panels: { defaults: [] },
  })
}

async function selectEmail(type) {
  if (type === currentType) return
  currentType = type

  setActiveBtn(type)
  updateVariablesPanel(type)
  setStatus('Loading…')
  document.getElementById('ee-save').disabled = true
  document.getElementById('ee-reset').disabled = true

  try {
    const { mjml, is_custom } = await loadTemplate(type)
    initEditor(mjml)
    setBadge(type, is_custom)
    setStatus(is_custom ? 'Showing custom template' : 'Showing default template — save to customise', 'info')
    document.getElementById('ee-save').disabled = false
    document.getElementById('ee-reset').disabled = !is_custom
  } catch (err) {
    setStatus('Failed to load template: ' + err.message, 'error')
  }
}

async function handleSave() {
  if (!currentType || !editor || isSaving) return
  isSaving = true
  document.getElementById('ee-save').disabled = true
  setStatus('Saving…')

  try {
    const mjml = editor.getHtml()
    await saveTemplate(currentType, mjml)
    setBadge(currentType, true)
    document.getElementById('ee-reset').disabled = false
    setStatus('Saved successfully!', 'success')
  } catch (err) {
    setStatus('Save failed: ' + err.message, 'error')
  } finally {
    isSaving = false
    document.getElementById('ee-save').disabled = false
  }
}

async function handleReset() {
  if (!currentType || !editor) return
  if (!window.confirm('Reset to the default template? Your customisation will be deleted.')) return

  document.getElementById('ee-reset').disabled = true
  setStatus('Resetting…')

  try {
    await resetTemplate(currentType)
    setBadge(currentType, false)
    const { mjml } = await loadTemplate(currentType)
    initEditor(mjml)
    document.getElementById('ee-reset').disabled = true // no custom anymore
    setStatus('Reset to default template', 'info')
  } catch (err) {
    setStatus('Reset failed: ' + err.message, 'error')
    document.getElementById('ee-reset').disabled = false
  }
}

// ─── Init ─────────────────────────────────────────────────────────────────────

buildSidebar()
document.getElementById('ee-save').addEventListener('click', handleSave)
document.getElementById('ee-reset').addEventListener('click', handleReset)

// Auto-select first email type
const firstType = Object.keys(emailTypes)[0]
if (firstType) {
  selectEmail(firstType)
}
