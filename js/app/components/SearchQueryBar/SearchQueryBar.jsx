import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Button, Checkbox, DatePicker, Input, Spin, Tag } from 'antd'
import dayjs from 'dayjs'
import localeData from 'dayjs/plugin/localeData'
import weekday from 'dayjs/plugin/weekday'
import debounce from 'lodash/debounce'
import { useTranslation } from 'react-i18next'

import { datePickerProps } from '../../utils/antd'
import {
  canonicalizeToken,
  hasUnterminatedQuote,
  isBareKeyToken,
  isGroupValue,
  parseGroupValues,
  parseLiveToken,
  parseToken,
  serializeFilterToken,
  tokenize,
  unquote,
} from './queryString'

// antd's DatePicker (rc-picker) calls dayjs(...).weekday()/.localeData() for
// calendar navigation (e.g. arrow keys) - without these plugins extended,
// that throws "clone.weekday is not a function".
dayjs.extend(weekday)
dayjs.extend(localeData)

const DATE_VALUE_FORMAT = 'YYYY-MM-DD'
const DATE_VALUE_RE = /^\d{4}-\d{2}-\d{2}$/

/**
 * A single search bar with a Sentry/Datadog-like query language:
 * - "key:value" filters a field, "-key:value" excludes it
 * - anything else is a free-text term, used for fuzzy search
 * - autocomplete suggests known field keys, then values for that field
 * - finished query parts are shown as removable tags, Sentry-style
 *
 * `fields` describes what can be filtered on:
 *   [{
 *     key: 'state',                 // the "key" used in the query string
 *     label: 'State',               // shown in the key suggestion list
 *     negatable: true,              // whether "-key:value" is offered (default: true)
 *     type: 'enum',                 // 'enum' (static options), 'async' (loadOptions) or 'date'
 *     options: [{ label, value }],  // for type: 'enum'
 *     loadOptions: (input) => Promise<[{ label, value }]>, // for type: 'async'
 *     multi: true,                  // Sentry-style checkbox dropdown, builds
 *                                    // "key:(v1 OR v2)" - 'enum'/'async' only
 *   }, ...]                        // type: 'date' shows a date picker; no options/loadOptions needed
 *
 * The component is uncontrolled with respect to parsing: it only ever
 * produces/consumes the raw query string, via `defaultValue` and `onSearch`.
 *
 * Pass `scope` (e.g. "orders") to turn on saving/reusing queries for this
 * bar - a name picker to save the current query, and a list to reapply a
 * saved one - backed by the generic AppBundle\Entity\SearchQuery API
 * (POST /api/search_queries, GET /api/me/search_queries?scope=..., DELETE
 * /api/search_queries/{id}). Omit it to leave the bar without this feature.
 */
export default function SearchQueryBar({ fields, defaultValue = '', onSearch, placeholder, scope }) {
  const { t } = useTranslation()

  // Finished query parts (rendered as tags) and the one still being typed.
  const [committedTokens, setCommittedTokens] = useState(() => tokenize(defaultValue).map(canonicalizeToken))
  const [draft, setDraft] = useState('')
  // Set while editing an existing tag (clicked, or popped via Backspace):
  // the index in committedTokens the draft should be reinserted at once
  // finished, so editing a tag doesn't reorder it to the end of the query.
  const [editingIndex, setEditingIndex] = useState(null)
  const [isOpen, setIsOpen] = useState(false)
  const [highlightedIndex, setHighlightedIndex] = useState(0)
  const [asyncOptions, setAsyncOptions] = useState([])
  const [isLoadingAsyncOptions, setIsLoadingAsyncOptions] = useState(false)
  // Checked { value, label } options while editing a `multi: true` field's
  // value - see applySuggestion()/toggleMultiValue() and the checkbox
  // suggestion rows below. Reset once the edit is committed or abandoned.
  const [multiSelection, setMultiSelection] = useState([])

  // Saved searches (only used when `scope` is set) - see the class doc.
  const [savedSearches, setSavedSearches] = useState([])
  const [isLoadingSavedSearches, setIsLoadingSavedSearches] = useState(false)
  const [isSavedSearchesOpen, setIsSavedSearchesOpen] = useState(false)
  const [isSaveOpen, setIsSaveOpen] = useState(false)
  const [saveName, setSaveName] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  const inputRef = useRef(null)
  const containerRef = useRef(null)
  const httpClientRef = useRef(null)

  const fieldsByKey = useMemo(() => {
    const map = {}
    fields.forEach(field => { map[field.key] = field })
    return map
  }, [fields])

  const liveToken = useMemo(() => parseLiveToken(draft), [draft])

  const activeField = liveToken.isFilter ? fieldsByKey[liveToken.key] : null

  const loadAsyncOptions = useCallback(
    debounce(async (field, input) => {
      setIsLoadingAsyncOptions(true)
      try {
        const options = await field.loadOptions(input)
        setAsyncOptions(options || [])
      } finally {
        setIsLoadingAsyncOptions(false)
      }
    }, 300),
    [],
  )

  useEffect(() => {
    if (activeField && activeField.type === 'async') {
      loadAsyncOptions(activeField, liveToken.value || '')
    }
  }, [activeField, liveToken.value, loadAsyncOptions])

  // What to show in the dropdown: field keys, or values for the active field.
  const suggestions = useMemo(() => {
    if (!liveToken.isFilter) {
      const keyword = liveToken.raw.replace(/^-/, '').toLowerCase()
      return fields
        .filter(field => field.key.toLowerCase().includes(keyword))
        .map(field => ({
          type: 'key',
          key: `key:${field.key}`,
          label: field.label,
          insert: `${liveToken.exclude ? '-' : ''}${field.key}:`,
        }))
    }

    if (!activeField || activeField.type === 'date') {
      return []
    }

    const keyword = (liveToken.value || '').toLowerCase()
    const options = activeField.type === 'async' ? asyncOptions : (activeField.options || [])
    const filteredOptions = options
      .filter(option => activeField.type === 'async' || String(option.label).toLowerCase().includes(keyword))

    if (activeField.multi) {
      // A value keeps its position in the list when checked/unchecked - only
      // a checked value that has fallen out of the current results (e.g. the
      // search text changed) gets pinned above them, so it isn't lost.
      const selectedValues = new Set(multiSelection.map(option => option.value))
      const filteredValues = new Set(filteredOptions.map(option => option.value))
      const pinnedRows = multiSelection
        .filter(option => !filteredValues.has(option.value))
        .map(option => ({
          type: 'checkbox',
          key: `value:${option.value}`,
          value: option.value,
          label: option.label,
          checked: true,
        }))
      const rows = filteredOptions.map(option => ({
        type: 'checkbox',
        key: `value:${option.value}`,
        value: option.value,
        label: option.label,
        checked: selectedValues.has(option.value),
      }))
      return [...pinnedRows, ...rows]
    }

    return filteredOptions.map(option => ({
      type: 'value',
      key: `value:${option.value}`,
      label: option.label,
      insert: serializeFilterToken({ key: activeField.key, value: option.value, exclude: liveToken.exclude }),
    }))
  }, [liveToken, activeField, fields, asyncOptions, multiSelection])

  useEffect(() => {
    setHighlightedIndex(0)
  }, [suggestions])

  const focusInput = () => {
    requestAnimationFrame(() => inputRef.current?.focus())
  }

  const removeToken = (index) => {
    setCommittedTokens(prev => prev.filter((_, i) => i !== index))
    // Keep an in-progress edit pointed at the right slot if a tag before it
    // just got removed (shifting every later index down by one).
    setEditingIndex(prev => (prev !== null && index < prev ? prev - 1 : prev))
    focusInput()
  }

  // Commits one or more finished tokens - appending them normally, or
  // reinserting them at `editingIndex` when the draft came from editing an
  // existing tag, so editing doesn't reorder it to the end of the query.
  const commitTokens = useCallback((newTokens) => {
    if (newTokens.length > 0) {
      setCommittedTokens(prev => {
        if (editingIndex === null) {
          return [...prev, ...newTokens]
        }
        const next = [...prev]
        next.splice(editingIndex, 0, ...newTokens)
        return next
      })
    }
    setEditingIndex(null)
  }, [editingIndex])

  // Builds the token for whatever's currently being edited - the checked
  // options of a `multi: true` field, or the plain typed `draft` otherwise -
  // or null if there's nothing worth committing (see isBareKeyToken/empty
  // selection). Shared by submit() and onDraftBlur() so both finalize an
  // in-progress multi-value edit the same way.
  const buildPendingToken = () => {
    if (activeField?.multi && liveToken.isFilter) {
      return multiSelection.length > 0
        ? serializeFilterToken({ key: activeField.key, values: multiSelection.map(o => o.value), exclude: liveToken.exclude })
        : null
    }
    return draft && !isBareKeyToken(draft) ? draft : null
  }

  const submit = useCallback(() => {
    setIsOpen(false)
    let finalTokens = committedTokens
    const pending = buildPendingToken()
    if (pending) {
      finalTokens = editingIndex === null
        ? [...committedTokens, pending]
        : [...committedTokens.slice(0, editingIndex), pending, ...committedTokens.slice(editingIndex)]
      setCommittedTokens(finalTokens)
      setDraft('')
      setMultiSelection([])
      setEditingIndex(null)
    }
    onSearch(finalTokens.join(' '))
  }, [committedTokens, draft, editingIndex, onSearch, activeField, liveToken, multiSelection])

  const clearAll = () => {
    setCommittedTokens([])
    setDraft('')
    setEditingIndex(null)
    setIsOpen(false)
    onSearch('')
  }

  // The full query as it would be submitted right now, including whatever's
  // still being typed (mirrors submit()'s composition).
  const currentQueryString = () => {
    const pending = buildPendingToken()
    const tokens = pending ? [...committedTokens, pending] : committedTokens
    return tokens.join(' ')
  }

  const getHttpClient = () => {
    if (!httpClientRef.current) {
      httpClientRef.current = new window._auth.httpClient()
    }
    return httpClientRef.current
  }

  const loadSavedSearches = useCallback(async () => {
    setIsLoadingSavedSearches(true)
    try {
      const { response } = await getHttpClient().get(`/api/me/search_queries?scope=${encodeURIComponent(scope)}`)
      setSavedSearches(response?.['hydra:member'] || [])
    } finally {
      setIsLoadingSavedSearches(false)
    }
  }, [scope])

  const toggleSavedSearches = () => {
    const next = !isSavedSearchesOpen
    setIsOpen(false)
    setIsSaveOpen(false)
    setIsSavedSearchesOpen(next)
    if (next) {
      loadSavedSearches()
    }
  }

  const applySavedSearch = (item) => {
    setCommittedTokens(tokenize(item.query).map(canonicalizeToken))
    setDraft('')
    setEditingIndex(null)
    setIsSavedSearchesOpen(false)
    onSearch(item.query)
  }

  const deleteSavedSearch = async (item, e) => {
    e.stopPropagation()
    setSavedSearches(prev => prev.filter(s => s['@id'] !== item['@id']))
    await getHttpClient().delete(item['@id'])
  }

  const openSaveForm = () => {
    setIsOpen(false)
    setIsSavedSearchesOpen(false)
    setSaveName('')
    setIsSaveOpen(true)
  }

  const submitSave = async () => {
    const query = currentQueryString()
    const name = saveName.trim()
    if (!name || !query || isSaving) {
      return
    }
    setIsSaving(true)
    try {
      const { response } = await getHttpClient().post('/api/search_queries', { scope, query, name })
      setSavedSearches(prev => [response, ...prev])
      setIsSaveOpen(false)
    } finally {
      setIsSaving(false)
    }
  }

  // Clicking a tag pulls it back out for editing, right where it was -
  // matches Sentry. Ignored while another tag is already being edited, so
  // switching targets mid-edit can't silently drop the first edit. A
  // `multi: true` field's tag reopens the checkbox dropdown with its
  // current values pre-checked, rather than the raw "key:(...)" text.
  const editToken = (index) => {
    if (editingIndex !== null) {
      return
    }
    const raw = committedTokens[index]
    const parsed = parseToken(raw)
    const field = parsed.isFilter ? fieldsByKey[parsed.key] : null

    if (field?.multi) {
      setMultiSelection(parseGroupValues(parsed.value).map(value => ({ value, label: value })))
      setDraft(`${parsed.exclude ? '-' : ''}${parsed.key}:`)
    } else {
      setDraft(raw)
    }
    setCommittedTokens(prev => prev.filter((_, i) => i !== index))
    setEditingIndex(index)
    setIsOpen(true)
    focusInput()
  }

  // Toggles one option of a `multi: true` field's checkbox dropdown - keeps
  // the dropdown open (unlike applySuggestion()) so several values can be
  // checked in a row; the resulting "key:(v1 OR v2)" token is only built
  // once the edit is finished (see buildPendingToken()).
  const toggleMultiValue = (suggestion) => {
    setMultiSelection(prev => (
      prev.some(o => o.value === suggestion.value)
        ? prev.filter(o => o.value !== suggestion.value)
        : [...prev, { value: suggestion.value, label: suggestion.label }]
    ))
  }

  // Key suggestions insert a bare "key:" that still needs a value typed
  // right after it, so it stays in the draft. Value suggestions (and dates)
  // insert a complete "key:value" token, so they're committed as a tag.
  const applySuggestion = (suggestion) => {
    if (suggestion.type === 'key') {
      setDraft(suggestion.insert)
    } else {
      commitTokens([suggestion.insert])
      setDraft('')
    }
    setIsOpen(true)
    focusInput()
  }

  const applyDate = (date) => {
    if (!date) {
      return
    }
    applySuggestion({
      insert: serializeFilterToken({ key: activeField.key, value: date.format(DATE_VALUE_FORMAT), exclude: liveToken.exclude }),
    })
  }

  const onKeyDown = (e) => {
    if (e.key === 'Backspace' && draft === '' && committedTokens.length > 0 && editingIndex === null) {
      // Pop the last tag back into the draft for editing, same convention
      // as most tag inputs (Gmail's "To" field, GitHub labels, etc.).
      e.preventDefault()
      editToken(committedTokens.length - 1)
      return
    }
    if (e.key === 'ArrowDown') {
      if (suggestions.length > 0) {
        e.preventDefault()
        setIsOpen(true)
        setHighlightedIndex((i) => (i + 1) % suggestions.length)
      }
      return
    }
    if (e.key === 'ArrowUp') {
      if (suggestions.length > 0) {
        e.preventDefault()
        setIsOpen(true)
        setHighlightedIndex((i) => (i - 1 + suggestions.length) % suggestions.length)
      }
      return
    }
    if (e.key === 'Escape') {
      setIsOpen(false)
      return
    }
    if (e.key === 'Enter') {
      e.preventDefault()
      // Only treat Enter as "accept the highlighted suggestion" when the
      // user is actually mid-token (draft !== ''). Otherwise the draft is
      // empty and just showing the full field list by default (e.g. right
      // after finishing a value), and Enter should submit as-is instead of
      // inserting an unwanted filter.
      if (isOpen && suggestions.length > 0 && draft !== '') {
        // A multi-value field's row toggles its checkbox and keeps the
        // dropdown open, instead of committing+closing like a normal value.
        if (suggestions[highlightedIndex].type === 'checkbox') {
          toggleMultiValue(suggestions[highlightedIndex])
        } else {
          applySuggestion(suggestions[highlightedIndex])
        }
      } else {
        submit()
      }
    }
  }

  // When the bar loses focus, whatever's left in the draft is committed
  // back into a tag - this is what takes an edited tag out of its "editing"
  // (plain text) state once you click away. Suggestion rows and the date
  // picker guard against this firing on their own clicks (onMouseDown +
  // preventDefault), so this only fires for a genuine loss of focus.
  const onDraftBlur = () => {
    const pending = buildPendingToken()
    commitTokens(pending ? [pending] : [])
    setDraft('')
    setMultiSelection([])
    setIsOpen(false)
  }

  const onDraftChange = (e) => {
    const value = e.target.value

    if (hasUnterminatedQuote(value)) {
      // Mid-way through typing a quoted (possibly multi-word) value - keep
      // it whole in the draft rather than splitting on the space(s) inside.
      setDraft(value)
      setIsOpen(true)
      return
    }

    const tokens = tokenize(value)
    const endsWithSpace = /\s$/.test(value)

    if (endsWithSpace) {
      // A trailing bare "key:" (no value yet, e.g. hitting space right after
      // picking a field) isn't finished - keep it in the draft instead of
      // committing it as a nonsense tag, absorbing the accidental space.
      const lastToken = tokens[tokens.length - 1]
      const stillTyping = tokens.length > 0 && isBareKeyToken(lastToken)

      commitTokens((stillTyping ? tokens.slice(0, -1) : tokens).map(canonicalizeToken))
      setDraft(stillTyping ? lastToken : '')
    } else if (tokens.length > 1) {
      commitTokens(tokens.slice(0, -1).map(canonicalizeToken))
      setDraft(tokens[tokens.length - 1])
    } else {
      setDraft(tokens[0] || '')
    }
    setIsOpen(true)
  }

  // Close every dropdown/panel when clicking outside the component.
  useEffect(() => {
    const onClickOutside = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setIsOpen(false)
        setIsSavedSearchesOpen(false)
        setIsSaveOpen(false)
      }
    }
    document.addEventListener('mousedown', onClickOutside)
    return () => document.removeEventListener('mousedown', onClickOutside)
  }, [])

  const draftInput = (
    <input
      ref={inputRef}
      type="text"
      value={draft}
      placeholder={committedTokens.length === 0 ? (placeholder || t('SEARCH_QUERY_BAR_PLACEHOLDER')) : ''}
      onChange={onDraftChange}
      onKeyDown={onKeyDown}
      onFocus={() => { setIsOpen(true); setIsSavedSearchesOpen(false); setIsSaveOpen(false) }}
      onBlur={onDraftBlur}
      style={{ flex: 1, minWidth: 80, border: 'none', outline: 'none', fontFamily: 'monospace', fontSize: 15 }}
    />
  )

  return (
    <div ref={containerRef} style={{ position: 'relative', width: '100%' }}>
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          flexWrap: 'wrap',
          rowGap: 6,
          border: '1px solid #d9d9d9',
          borderRadius: 6,
          padding: '7px 10px',
          background: '#fff',
        }}
        onClick={() => inputRef.current?.focus()}
      >
        <i className="fa fa-search" style={{ color: '#aaa', marginRight: 8, fontSize: 15 }} />
        {committedTokens.map((raw, index) => {
          const parsed = parseToken(raw)
          return (
            <Tag
              key={index}
              closable
              onClick={() => editToken(index)}
              onClose={(e) => { e.preventDefault(); e.stopPropagation(); removeToken(index) }}
              style={{ marginInlineEnd: 4, cursor: 'pointer', fontSize: 14, padding: '3px 9px', lineHeight: '18px' }}
            >
              {parsed.isFilter ? (
                <>
                  {parsed.exclude && <span style={{ color: '#cf1322' }}>-</span>}
                  <span>{parsed.key}</span>
                  <span style={{ color: '#aaa' }}>:</span>
                  <span style={{ color: '#1677ff', fontWeight: 500 }}>
                    {isGroupValue(parsed.value) ? parseGroupValues(parsed.value).join(' OR ') : unquote(parsed.value)}
                  </span>
                </>
              ) : (
                <span>{unquote(raw)}</span>
              )}
            </Tag>
          )
        })}
        {draftInput}
        {(committedTokens.length > 0 || draft) && (
          <i
            className="fa fa-times"
            role="button"
            aria-label={t('SEARCH_QUERY_BAR_CLEAR')}
            style={{ color: '#aaa', cursor: 'pointer', fontSize: 15 }}
            onClick={(e) => { e.stopPropagation(); clearAll() }}
          />
        )}
        {scope && (
          <>
            <i
              className="fa fa-star-o"
              role="button"
              aria-label={t('SEARCH_QUERY_BAR_SAVE')}
              title={t('SEARCH_QUERY_BAR_SAVE')}
              style={{ color: '#aaa', cursor: 'pointer', fontSize: 15, marginLeft: 10 }}
              onClick={(e) => { e.stopPropagation(); openSaveForm() }}
            />
            <i
              className="fa fa-bookmark-o"
              role="button"
              aria-label={t('SEARCH_QUERY_BAR_SAVED_SEARCHES')}
              title={t('SEARCH_QUERY_BAR_SAVED_SEARCHES')}
              style={{ color: '#aaa', cursor: 'pointer', fontSize: 15, marginLeft: 10 }}
              onClick={(e) => { e.stopPropagation(); toggleSavedSearches() }}
            />
          </>
        )}
      </div>
      {isOpen && activeField && activeField.type === 'date' && (
        <div
          // Without this, clicking a calendar day (or the prev/next month
          // arrows) blurs the draft input first, which would commit/close
          // before the DatePicker's own onChange gets a chance to fire -
          // same reason the suggestion rows below guard their own clicks.
          // Capture phase so it runs before antd's own internal handlers,
          // in case one of them stops the event from bubbling back up.
          onMouseDownCapture={(e) => e.preventDefault()}
          style={{
            position: 'absolute',
            zIndex: 1000,
            top: '100%',
            left: 0,
            marginTop: 4,
            background: '#fff',
            border: '1px solid #d9d9d9',
            borderRadius: 4,
            boxShadow: '0 2px 8px rgba(0, 0, 0, 0.1)',
            padding: 8,
          }}
        >
          <DatePicker
            open
            format={datePickerProps.format}
            value={liveToken.value && DATE_VALUE_RE.test(liveToken.value) ? dayjs(liveToken.value) : null}
            onChange={applyDate}
            getPopupContainer={(trigger) => trigger.parentElement}
          />
        </div>
      )}
      {isOpen && (!activeField || activeField.type !== 'date') && (suggestions.length > 0 || isLoadingAsyncOptions) && (
        <div
          style={{
            position: 'absolute',
            zIndex: 1000,
            top: '100%',
            left: 0,
            right: 0,
            marginTop: 4,
            background: '#fff',
            border: '1px solid #d9d9d9',
            borderRadius: 4,
            boxShadow: '0 2px 8px rgba(0, 0, 0, 0.1)',
            maxHeight: 260,
            overflowY: 'auto',
          }}
        >
          {isLoadingAsyncOptions && suggestions.length === 0 && (
            <div style={{ padding: '8px 12px' }}><Spin size="small" /></div>
          )}
          {suggestions.map((suggestion, index) => (
            <div
              key={suggestion.key}
              onMouseDown={(e) => {
                e.preventDefault()
                if (suggestion.type === 'checkbox') {
                  // Keeps the dropdown open, unlike applySuggestion() -
                  // several values can be checked in a row.
                  toggleMultiValue(suggestion)
                  setHighlightedIndex(index)
                } else {
                  applySuggestion(suggestion)
                }
              }}
              onMouseEnter={() => setHighlightedIndex(index)}
              style={{
                padding: '6px 12px',
                cursor: 'pointer',
                background: index === highlightedIndex ? '#f5f5f5' : 'transparent',
              }}
            >
              {suggestion.type === 'key' ? (
                <><strong>{suggestion.key.slice(4)}</strong><span style={{ color: '#aaa' }}> — {suggestion.label}</span></>
              ) : suggestion.type === 'checkbox' ? (
                <label style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                  <Checkbox checked={suggestion.checked} onChange={() => {}} />
                  <span>{suggestion.label}</span>
                </label>
              ) : (
                <>{suggestion.label}</>
              )}
            </div>
          ))}
        </div>
      )}
      {isSaveOpen && (
        <div
          style={{
            position: 'absolute',
            zIndex: 1000,
            top: '100%',
            left: 0,
            right: 0,
            marginTop: 4,
            background: '#fff',
            border: '1px solid #d9d9d9',
            borderRadius: 4,
            boxShadow: '0 2px 8px rgba(0, 0, 0, 0.1)',
            padding: 12,
          }}
        >
          <Input
            autoFocus
            placeholder={t('SEARCH_QUERY_BAR_SAVE_NAME_PLACEHOLDER')}
            value={saveName}
            onChange={(e) => setSaveName(e.target.value)}
            onPressEnter={submitSave}
            style={{ marginBottom: 8 }}
          />
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
            <Button size="small" onClick={() => setIsSaveOpen(false)}>{t('CANCEL')}</Button>
            <Button
              size="small"
              type="primary"
              loading={isSaving}
              disabled={!saveName.trim()}
              onClick={submitSave}
            >
              {t('SEARCH_QUERY_BAR_SAVE')}
            </Button>
          </div>
        </div>
      )}
      {isSavedSearchesOpen && (
        <div
          style={{
            position: 'absolute',
            zIndex: 1000,
            top: '100%',
            left: 0,
            right: 0,
            marginTop: 4,
            background: '#fff',
            border: '1px solid #d9d9d9',
            borderRadius: 4,
            boxShadow: '0 2px 8px rgba(0, 0, 0, 0.1)',
            maxHeight: 320,
            overflowY: 'auto',
          }}
        >
          {isLoadingSavedSearches && (
            <div style={{ padding: '12px' }}><Spin size="small" /></div>
          )}
          {!isLoadingSavedSearches && savedSearches.length === 0 && (
            <div style={{ padding: '10px 12px', color: '#aaa' }}>{t('SEARCH_QUERY_BAR_NO_SAVED_SEARCHES')}</div>
          )}
          {savedSearches.map((item) => (
            <div
              key={item['@id']}
              onClick={() => applySavedSearch(item)}
              style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 8,
                padding: '8px 12px',
                cursor: 'pointer',
              }}
              onMouseEnter={(e) => { e.currentTarget.style.background = '#f5f5f5' }}
              onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent' }}
            >
              <div style={{ overflow: 'hidden' }}>
                <div style={{ fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.name}</div>
                <div style={{ fontFamily: 'monospace', fontSize: 12, color: '#aaa', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{item.query}</div>
              </div>
              <i
                className="fa fa-trash-o"
                role="button"
                aria-label={t('SEARCH_QUERY_BAR_DELETE_SAVED_SEARCH')}
                style={{ color: '#aaa', cursor: 'pointer', flexShrink: 0 }}
                onClick={(e) => deleteSavedSearch(item, e)}
              />
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
