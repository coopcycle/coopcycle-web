import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Spin } from 'antd'
import debounce from 'lodash/debounce'
import { useTranslation } from 'react-i18next'

import {
  getTokenAtCursor,
  replaceTokenAtCursor,
  serializeFilterToken,
} from './queryString'

/**
 * A single search bar with a Sentry/Datadog-like query language:
 * - "key:value" filters a field, "-key:value" excludes it
 * - anything else is a free-text term, used for fuzzy search
 * - autocomplete suggests known field keys, then values for that field
 *
 * `fields` describes what can be filtered on:
 *   [{
 *     key: 'state',                 // the "key" used in the query string
 *     label: 'State',               // shown in the key suggestion list
 *     negatable: true,              // whether "-key:value" is offered (default: true)
 *     type: 'enum',                 // 'enum' (static options) or 'async' (loadOptions)
 *     options: [{ label, value }],  // for type: 'enum'
 *     loadOptions: (input) => Promise<[{ label, value }]>, // for type: 'async'
 *   }, ...]
 *
 * The component is uncontrolled with respect to parsing: it only ever
 * produces/consumes the raw query string, via `defaultValue` and `onSearch`.
 */
export default function SearchQueryBar({ fields, defaultValue = '', onSearch, placeholder }) {
  const { t } = useTranslation()

  const [text, setText] = useState(defaultValue)
  const [cursor, setCursor] = useState(defaultValue.length)
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

  const token = useMemo(() => getTokenAtCursor(text, cursor), [text, cursor])

  const activeField = token.isFilter ? fieldsByKey[token.key] : null

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
      loadAsyncOptions(activeField, token.value || '')
    }
  }, [activeField, token.value, loadAsyncOptions])

  // What to show in the dropdown: field keys, or values for the active field.
  const suggestions = useMemo(() => {
    if (!token.isFilter) {
      const keyword = token.raw.replace(/^-/, '').toLowerCase()
      return fields
        .filter(field => field.key.toLowerCase().includes(keyword))
        .map(field => ({
          type: 'key',
          key: `key:${field.key}`,
          label: field.label,
          insert: `${token.exclude ? '-' : ''}${field.key}:`,
        }))
    }

    if (!activeField) {
      return []
    }

    const keyword = (token.value || '').toLowerCase()
    const options = activeField.type === 'async' ? asyncOptions : (activeField.options || [])

    return options
      .filter(option => activeField.type === 'async' || String(option.label).toLowerCase().includes(keyword))
      .map(option => ({
        type: 'value',
        key: `value:${option.value}`,
        label: option.label,
        insert: serializeFilterToken({ key: activeField.key, value: option.value, exclude: token.exclude }),
      }))
  }, [token, activeField, fields, asyncOptions])

  useEffect(() => {
    setHighlightedIndex(0)
  }, [suggestions])

  const submit = useCallback((value) => {
    setIsOpen(false)
    onSearch(value.trim())
  }, [onSearch])

  const applySuggestion = (suggestion) => {
    const { text: newText, cursor: newCursor } = replaceTokenAtCursor(text, cursor, suggestion.insert)
    setText(newText)
    setCursor(newCursor)
    setIsOpen(true)
    // Re-focus & move the caret, since selecting a suggestion via click blurs the input.
    requestAnimationFrame(() => {
      if (inputRef.current) {
        inputRef.current.focus()
        inputRef.current.setSelectionRange(newCursor, newCursor)
      }
    })
  }

  const onKeyDown = (e) => {
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
      if (isOpen && suggestions.length > 0) {
        applySuggestion(suggestions[highlightedIndex])
      } else {
        submit(text)
      }
    }
  }

  const onInputChange = (e) => {
    setText(e.target.value)
    setCursor(e.target.selectionStart)
    setIsOpen(true)
  }

  const onClickOrKeyUp = (e) => {
    setCursor(e.target.selectionStart)
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

  return (
    <div ref={containerRef} style={{ position: 'relative', width: '100%' }}>
      <div style={{ display: 'flex', alignItems: 'center', border: '1px solid #d9d9d9', borderRadius: 4, padding: '4px 8px', background: '#fff' }}>
        <i className="fa fa-search" style={{ color: '#aaa', marginRight: 8 }} />
        <input
          ref={inputRef}
          type="text"
          value={text}
          placeholder={placeholder || t('SEARCH_QUERY_BAR_PLACEHOLDER')}
          onChange={onInputChange}
          onKeyDown={onKeyDown}
          onKeyUp={onClickOrKeyUp}
          onClick={onClickOrKeyUp}
          onFocus={() => setIsOpen(true)}
          style={{ flex: 1, border: 'none', outline: 'none', fontFamily: 'monospace', fontSize: '0.95em' }}
        />
        {text && (
          <i
            className="fa fa-times"
            role="button"
            aria-label={t('SEARCH_QUERY_BAR_CLEAR')}
            style={{ color: '#aaa', cursor: 'pointer' }}
            onClick={() => { setText(''); setCursor(0); submit('') }}
          />
        )}
      </div>
      {isOpen && (suggestions.length > 0 || isLoadingAsyncOptions) && (
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
