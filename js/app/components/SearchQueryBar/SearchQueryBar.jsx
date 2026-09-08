import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { DatePicker, Spin, Tag } from 'antd'
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
 *   }, ...]                        // type: 'date' shows a date picker; no options/loadOptions needed
 *
 * The component is uncontrolled with respect to parsing: it only ever
 * produces/consumes the raw query string, via `defaultValue` and `onSearch`.
 */
export default function SearchQueryBar({ fields, defaultValue = '', onSearch, placeholder }) {
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

  const inputRef = useRef(null)
  const containerRef = useRef(null)

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

    return options
      .filter(option => activeField.type === 'async' || String(option.label).toLowerCase().includes(keyword))
      .map(option => ({
        type: 'value',
        key: `value:${option.value}`,
        label: option.label,
        insert: serializeFilterToken({ key: activeField.key, value: option.value, exclude: liveToken.exclude }),
      }))
  }, [liveToken, activeField, fields, asyncOptions])

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

  const submit = useCallback(() => {
    setIsOpen(false)
    let finalTokens = committedTokens
    // A bare "key:" with no value isn't a real filter (see isBareKeyToken) -
    // drop it rather than submitting it as a nonsense token.
    if (draft && !isBareKeyToken(draft)) {
      finalTokens = editingIndex === null
        ? [...committedTokens, draft]
        : [...committedTokens.slice(0, editingIndex), draft, ...committedTokens.slice(editingIndex)]
      setCommittedTokens(finalTokens)
      setDraft('')
      setEditingIndex(null)
    }
    onSearch(finalTokens.join(' '))
  }, [committedTokens, draft, editingIndex, onSearch])

  const clearAll = () => {
    setCommittedTokens([])
    setDraft('')
    setEditingIndex(null)
    setIsOpen(false)
    onSearch('')
  }

  // Clicking a tag pulls it back out for editing, right where it was -
  // matches Sentry. Ignored while another tag is already being edited, so
  // switching targets mid-edit can't silently drop the first edit.
  const editToken = (index) => {
    if (editingIndex !== null) {
      return
    }
    setDraft(committedTokens[index])
    setCommittedTokens(prev => prev.filter((_, i) => i !== index))
    setEditingIndex(index)
    setIsOpen(true)
    focusInput()
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
        applySuggestion(suggestions[highlightedIndex])
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
    // A bare "key:" with no value isn't a real filter (see isBareKeyToken) -
    // discard it rather than committing it as a nonsense tag.
    commitTokens(draft && !isBareKeyToken(draft) ? [draft] : [])
    setDraft('')
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

  // Close the dropdown when clicking outside the component.
  useEffect(() => {
    const onClickOutside = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        setIsOpen(false)
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
      onFocus={() => setIsOpen(true)}
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
                  <span style={{ color: '#1677ff', fontWeight: 500 }}>{unquote(parsed.value)}</span>
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
              onMouseDown={(e) => { e.preventDefault(); applySuggestion(suggestion) }}
              onMouseEnter={() => setHighlightedIndex(index)}
              style={{
                padding: '6px 12px',
                cursor: 'pointer',
                background: index === highlightedIndex ? '#f5f5f5' : 'transparent',
              }}
            >
              {suggestion.type === 'key' ? (
                <><strong>{suggestion.key.slice(4)}</strong><span style={{ color: '#aaa' }}> — {suggestion.label}</span></>
              ) : (
                <>{suggestion.label}</>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
