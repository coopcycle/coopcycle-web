import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Button, Checkbox, DatePicker, Input, Segmented, Spin, Tag, Tooltip } from 'antd'
import dayjs from 'dayjs'
import localeData from 'dayjs/plugin/localeData'
import weekday from 'dayjs/plugin/weekday'
import debounce from 'lodash/debounce'
import { useTranslation } from 'react-i18next'

import { datePickerProps } from '../../utils/antd'
import {
  canonicalizeToken,
  hasExcludePrefix,
  hasUnterminatedQuote,
  isBareKeyToken,
  isGroupValue,
  isRangeValue,
  parseGroupValues,
  parseRangeValue,
  parseLiveToken,
  parseToken,
  sanitizeQuery,
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

// How many values a `multi: true` field's tag shows before collapsing the
// rest into "+N more" - keeps the tag (and the underlying query string)
// from growing unbounded, Sentry-style.
const MAX_VISIBLE_GROUP_VALUES = 2

function formatGroupValues(values) {
  if (values.length <= MAX_VISIBLE_GROUP_VALUES) {
    return values.join(' OR ')
  }
  const shown = values.slice(0, MAX_VISIBLE_GROUP_VALUES).join(' OR ')
  const remaining = values.length - MAX_VISIBLE_GROUP_VALUES
  return `${shown} +${remaining} more`
}

/**
 * A single search bar with a Sentry/Datadog-like query language:
 * - "key:value" filters a field, "-key:value" excludes it
 * - autocomplete suggests known field keys, then values for that field
 * - finished query parts are shown as removable tags, Sentry-style
 * - an incoming query (`defaultValue`, or a saved search) is sanitized:
 *   parts that don't filter on one of `fields` are dropped rather than
 *   shown as a tag that does nothing - see sanitizeQuery()
 *
 * `fields` describes what can be filtered on:
 *   [{
 *     key: 'state',                 // the "key" used in the query string
 *     label: 'State',               // shown in the key suggestion list
 *     negatable: true,              // whether "-key:value" is offered (default: true)
 *     type: 'enum',                 // 'enum' (static options), 'async' (loadOptions) or 'date'
 *     options: [{ label, value }],  // for type: 'enum'
 *     loadOptions: (input) => Promise<[{ label, value }]>, // for type: 'async'
 *                                    // an option may carry a `description`,
 *                                    // shown muted under its label
 *     multi: true,                  // Sentry-style checkbox dropdown, builds
 *                                    // "key:(v1 OR v2)" - 'enum'/'async' only
 *   }, ...]                        // type: 'date' shows a day/range date picker,
 *                                  // building "key:2026-09-25" or
 *                                  // "key:[2026-09-25 TO 2026-09-26]";
 *                                  // no options/loadOptions needed
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
  const [committedTokens, setCommittedTokens] = useState(
    () => sanitizeQuery(defaultValue, fields.map(field => field.key)))
  const [draft, setDraft] = useState('')
  // Set while editing an existing tag (clicked, or popped via Backspace):
  // the index in committedTokens the draft should be reinserted at once
  // finished, so editing a tag doesn't reorder it to the end of the query.
  const [editingIndex, setEditingIndex] = useState(null)
  const [isOpen, setIsOpen] = useState(false)
  const [highlightedIndex, setHighlightedIndex] = useState(0)
  // Set while the pointer is over a row's "Exclude" shortcut, so the row it
  // belongs to can preview what clicking would do (struck-through label).
  const [excludeHoverIndex, setExcludeHoverIndex] = useState(null)
  const [asyncOptions, setAsyncOptions] = useState([])
  const [isLoadingAsyncOptions, setIsLoadingAsyncOptions] = useState(false)
  // Checked { value, label } options while editing a `multi: true` field's
  // value - see applySuggestion()/toggleMultiValue() and the checkbox
  // suggestion rows below. Reset once the edit is committed or abandoned.
  const [multiSelection, setMultiSelection] = useState([])
  // Which date picker a `type: 'date'` field shows: 'day' or 'range'. null
  // means "follow the value being edited", so opening an existing
  // "date:[a TO b]" lands on the range picker without the user asking.
  const [dateMode, setDateMode] = useState(null)
  // The half-finished range while picking (first end chosen, second not).
  // RangePicker is controlled here, so without somewhere to keep the
  // interim selection every re-render would reset it from the query string
  // - which has no range in it yet - and the second click would just start
  // over. onChange only fires once both ends are set, so it never would.
  const [dateRangeDraft, setDateRangeDraft] = useState(null)

  // Saved searches (only used when `scope` is set) - see the class doc.
  const [savedSearches, setSavedSearches] = useState([])
  const [isLoadingSavedSearches, setIsLoadingSavedSearches] = useState(false)
  const [isSavedSearchesOpen, setIsSavedSearchesOpen] = useState(false)
  const [isSaveOpen, setIsSaveOpen] = useState(false)
  const [saveName, setSaveName] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  const inputRef = useRef(null)
  const containerRef = useRef(null)
  const datePickerRef = useRef(null)
  const httpClientRef = useRef(null)
  // The last query handed to onSearch - see emitSearch().
  const lastSearchRef = useRef(sanitizeQuery(defaultValue, fields.map(field => field.key)).join(' '))

  const fieldsByKey = useMemo(() => {
    const map = {}
    fields.forEach(field => { map[field.key] = field })
    return map
  }, [fields])

  const liveToken = useMemo(() => parseLiveToken(draft), [draft])

  const activeField = liveToken.isFilter ? fieldsByKey[liveToken.key] : null

  // Whether what's typed so far is the value of a filter on a field the bar
  // knows about, i.e. still being written - e.g. `owner:Les Inv`. Values
  // routinely contain spaces ("Les Invisibles"), so a space there belongs to
  // the value and mustn't end the token; the token is finished by picking a
  // suggestion, pressing Enter or leaving the bar, and gets quoted then (see
  // buildPendingToken()). Dates are left out: their value comes from the
  // picker and never holds a space.
  const isTypingFilterValue = (value) => {
    const live = parseLiveToken(value)
    const field = live.isFilter ? fieldsByKey[live.key] : null
    if (!field || field.type === 'date') {
      return false
    }
    // ...unless a known filter appears further on, as when a whole query is
    // pasted in ("-state:cancelled owner:les"): that's several tokens, not
    // one long value.
    return tokenize(value).slice(1).every(token => {
      // parseLiveToken, so a key whose value isn't typed yet ("state:")
      // already counts as the start of the next filter.
      const parsed = parseLiveToken(token)
      return !(parsed.isFilter && fieldsByKey[parsed.key])
    })
  }

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
      loadAsyncOptions(activeField, unquote((liveToken.value || '').trim()))
    }
  }, [activeField, liveToken.value, loadAsyncOptions])

  // What to show in the dropdown: field keys, or values for the active field.
  const suggestions = useMemo(() => {
    if (!liveToken.isFilter) {
      // Not liveToken.exclude: a draft of just "-" parses as free text, so
      // that flag is still false - see hasExcludePrefix().
      const exclude = hasExcludePrefix(liveToken.raw)
      const keyword = liveToken.raw.replace(/^-/, '').toLowerCase()
      return fields
        .filter(field => field.key.toLowerCase().includes(keyword))
        .map(field => ({
          type: 'key',
          key: `key:${field.key}`,
          field: field.key,
          label: field.label,
          insert: `${exclude ? '-' : ''}${field.key}:`,
        }))
    }

    if (!activeField || activeField.type === 'date') {
      return []
    }

    const keyword = unquote((liveToken.value || '').trim()).toLowerCase()
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
      value: option.value,
      label: option.label,
      description: option.description,
      insert: serializeFilterToken({ key: activeField.key, value: option.value, exclude: liveToken.exclude }),
    }))
  }, [liveToken, activeField, fields, asyncOptions, multiSelection])

  useEffect(() => {
    setHighlightedIndex(0)
    setExcludeHoverIndex(null)
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
  // selection). Shared by submit(), toggleMultiValue() and onDraftBlur() so
  // they all finalize an in-progress multi-value edit the same way; pass
  // `selection` to build it from a selection that hasn't been stored in
  // state yet (setState being asynchronous).
  const buildPendingToken = (selection = multiSelection) => {
    if (activeField?.multi && liveToken.isFilter && selection.length > 0) {
      return serializeFilterToken({ key: activeField.key, values: selection.map(o => o.value), exclude: liveToken.exclude })
    }
    // A `multi: true` field with nothing ticked falls through to the typed
    // draft: a value typed by hand ("owner:Les Zzz", matching no option)
    // would otherwise be dropped on the floor, emptying the filter.
    const typed = draft.trim()
    // canonicalizeToken(), not the raw draft: a value typed with spaces
    // ("owner:Les Invisibles") only survives being re-joined into the query
    // once quoted.
    return typed && !isBareKeyToken(typed) ? canonicalizeToken(typed) : null
  }

  // Puts `pending` (whatever is still being typed/ticked) back among the
  // finished tags - at the end, or at the tag's own position when one is
  // being edited, so editing doesn't reorder the query.
  const composeTokens = (pending) => {
    if (!pending) {
      return committedTokens
    }
    return editingIndex === null
      ? [...committedTokens, pending]
      : [...committedTokens.slice(0, editingIndex), pending, ...committedTokens.slice(editingIndex)]
  }

  // Every search the bar runs goes through here, so onDraftBlur() can tell
  // whether what it's about to submit has already been submitted - ticking
  // a checkbox searches straight away, and clicking away right after
  // shouldn't refetch the same thing.
  const emitSearch = (query) => {
    lastSearchRef.current = query
    onSearch(query)
  }

  const submit = useCallback((selectionOverride) => {
    // Guarded: submit() is also used as an event handler, which would
    // otherwise pass the event as the selection.
    const selection = Array.isArray(selectionOverride) ? selectionOverride : multiSelection
    setIsOpen(false)
    const pending = buildPendingToken(selection)
    const finalTokens = composeTokens(pending)
    if (pending) {
      setCommittedTokens(finalTokens)
      setDraft('')
      setMultiSelection([])
      setDateMode(null)
      setDateRangeDraft(null)
      setEditingIndex(null)
    }
    emitSearch(finalTokens.join(' '))
  }, [committedTokens, draft, editingIndex, onSearch, activeField, liveToken, multiSelection])

  const clearAll = () => {
    setCommittedTokens([])
    setDraft('')
    setEditingIndex(null)
    setIsOpen(false)
    emitSearch('')
  }

  // The full query as it would be submitted right now, including whatever's
  // still being typed (mirrors submit()'s composition).
  const currentQueryString = () => composeTokens(buildPendingToken()).join(' ')

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
    // Sanitized rather than applied verbatim: a search saved before a field
    // was renamed or removed would otherwise reintroduce a dead filter.
    const tokens = sanitizeQuery(item.query, Object.keys(fieldsByKey))
    setCommittedTokens(tokens)
    setDraft('')
    setEditingIndex(null)
    setIsSavedSearchesOpen(false)
    emitSearch(tokens.join(' '))
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
  // checked in a row. It deliberately doesn't run the search: that would
  // reload the page under the user mid-selection. The tick shows up in the
  // bar right away as a pending tag instead (see pendingTag below), and the
  // search runs once the filter is finished - label click, Enter or blur.
  const toggleMultiValue = (suggestion) => {
    setMultiSelection(prev => (
      prev.some(o => o.value === suggestion.value)
        ? prev.filter(o => o.value !== suggestion.value)
        : [...prev, { value: suggestion.value, label: suggestion.label }]
    ))
  }

  // Clicking a checkbox row's *label* (or pressing Enter on it) finishes
  // the whole filter rather than just ticking one more box: the row's value
  // is added to whatever is already ticked, the token is committed as a tag
  // and the search runs - same as picking a single-value suggestion.
  const applyMultiValue = (suggestion) => {
    const next = multiSelection.some(o => o.value === suggestion.value)
      ? multiSelection
      : [...multiSelection, { value: suggestion.value, label: suggestion.label }]
    submit(next)
  }

  // Key suggestions insert a bare "key:" that still needs a value typed
  // right after it, so it stays in the draft. Value suggestions (and dates)
  // insert a complete "key:value" token, so they're committed as a tag.
  const applySuggestion = (suggestion) => {
    if (suggestion.type === 'key') {
      setDraft(suggestion.insert)
    } else {
      // The tag it becomes is a finished filter, so the search runs right
      // away - a tag appearing while the results stay put reads as broken.
      const query = composeTokens(suggestion.insert).join(' ')
      commitTokens([suggestion.insert])
      setDraft('')
      emitSearch(query)
    }
    setIsOpen(true)
    focusInput()
  }

  // Applies a suggestion as an exclusion ("-owner:Foo") - what each row's
  // "Exclude" shortcut does, so the "-" prefix never has to be typed by
  // hand (it still can, see SEARCH_QUERY_BAR_HELP_EXCLUDE).
  const applyExclusion = (suggestion) => {
    if (suggestion.type === 'key') {
      setDraft(`-${suggestion.field}:`)
      setIsOpen(true)
      focusInput()
      return
    }

    // Excluding one value is a finished filter, on a `multi: true` field's
    // checkbox row as much as on a plain value row: it's committed as a tag
    // and searched straight away, rather than left pending like a tick.
    // That's what empties the bar of what was typed to find the value -
    // keeping a half-typed "-owner:la" next to the tag reads as if nothing
    // had been applied. Ticked-but-unfinished values are dropped with it;
    // they belong to the "include these" token being built, which this
    // exclusion replaces.
    setMultiSelection([])
    applySuggestion({
      ...suggestion,
      type: 'value',
      insert: serializeFilterToken({ key: activeField.key, value: suggestion.value, exclude: true }),
    })
  }

  // The bounds of the "date:[a TO b]" being edited, as the RangePicker
  // wants them - or a same-day range for a plain "date:2026-09-25", so
  // switching a single day over to "Range" starts from that day.
  const editedDateRange = useMemo(() => {
    const bounds = parseRangeValue(liveToken.value || '')
    if (bounds && DATE_VALUE_RE.test(bounds.from) && DATE_VALUE_RE.test(bounds.to)) {
      return [dayjs(bounds.from), dayjs(bounds.to)]
    }
    if (liveToken.value && DATE_VALUE_RE.test(liveToken.value)) {
      return [dayjs(liveToken.value), dayjs(liveToken.value)]
    }
    return null
  }, [liveToken.value])

  const effectiveDateMode = dateMode ?? (isRangeValue(liveToken.value || '') ? 'range' : 'day')

  // antd won't act on a calendar click until one of the picker's own inputs
  // is active - with a range, until it knows which end you're setting. Left
  // to itself the panel opens inactive (focus is still on the bar, or on
  // the Day/Range toggle), so the first click would be spent just
  // activating it. Hand the picker focus as soon as it's on screen.
  useEffect(() => {
    if (!isOpen || activeField?.type !== 'date') {
      return undefined
    }
    const frame = requestAnimationFrame(() => datePickerRef.current?.focus())
    return () => cancelAnimationFrame(frame)
  }, [isOpen, activeField, effectiveDateMode])

  const applyDate = (date) => {
    if (!date) {
      return
    }
    applySuggestion({
      insert: serializeFilterToken({ key: activeField.key, value: date.format(DATE_VALUE_FORMAT), exclude: liveToken.exclude }),
    })
    setDateMode(null)
    setDateRangeDraft(null)
  }

  const applyDateRange = (dates) => {
    const [from, to] = dates || []
    if (!from || !to) {
      return
    }
    applySuggestion({
      // serializeFilterToken collapses a same-day range back to the plain
      // "date:2026-09-25" form.
      insert: serializeFilterToken({
        key: activeField.key,
        range: { from: from.format(DATE_VALUE_FORMAT), to: to.format(DATE_VALUE_FORMAT) },
        exclude: liveToken.exclude,
      }),
    })
    setDateMode(null)
    setDateRangeDraft(null)
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
        // A multi-value field's row finishes the filter with that value
        // added to the ones already ticked - unless boxes *are* ticked, in
        // which case Enter submits those: the highlighted row is wherever
        // the pointer last passed, and adding it would be a surprise.
        if (suggestions[highlightedIndex].type === 'checkbox') {
          if (multiSelection.length > 0) {
            submit()
          } else {
            applyMultiValue(suggestions[highlightedIndex])
          }
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
  const onDraftBlur = (e) => {
    // ...except when focus lands on something inside the bar itself - the
    // date picker's Day/Range toggle, say. Preventing mousedown's default
    // isn't enough there: a control that takes focus on click (or focuses
    // itself programmatically, as antd's Segmented does) still blurs the
    // input, and committing here would empty the draft and unmount the very
    // panel being used.
    if (e?.relatedTarget && containerRef.current?.contains(e.relatedTarget)) {
      return
    }

    const pending = buildPendingToken()
    const query = composeTokens(pending).join(' ')
    commitTokens(pending ? [pending] : [])
    setDraft('')
    setMultiSelection([])
    setDateMode(null)
    setDateRangeDraft(null)
    setIsOpen(false)
    // Clicking away applies what's ticked/typed, instead of silently
    // keeping it out of the search - skipped when that exact query is
    // already the one showing (see emitSearch()).
    if (query !== lastSearchRef.current) {
      emitSearch(query)
    }
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

    if (isTypingFilterValue(value)) {
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

  // Documents the query language behind the "?" icon. The placeholder can
  // only carry a single example, and is hidden as soon as the bar holds a
  // tag - which it usually does, since bars start from a default query.
  // Examples are written generically ("key:value"), since this component
  // backs several search bars with different fields.
  const helpContent = (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      {[
        [t('SEARCH_QUERY_BAR_HELP_FIELDS'), 'key:value'],
        [t('SEARCH_QUERY_BAR_HELP_EXCLUDE'), '-key:value'],
        [t('SEARCH_QUERY_BAR_HELP_MULTI'), 'key:(value1 OR value2)'],
        [t('SEARCH_QUERY_BAR_HELP_RANGE'), 'date:[2026-09-25 TO 2026-09-26]'],
        [t('SEARCH_QUERY_BAR_HELP_QUOTE'), 'key:"a value with spaces"'],
        [t('SEARCH_QUERY_BAR_HELP_EDIT'), null],
        [t('SEARCH_QUERY_BAR_HELP_SUBMIT'), null],
      ].map(([text, example]) => (
        <div key={text}>
          <div>{text}</div>
          {example && (
            <div style={{ fontFamily: 'monospace', opacity: 0.75, marginTop: 2 }}>{example}</div>
          )}
        </div>
      ))}
    </div>
  )

  // The `multi: true` filter being built, shown as a dashed, non-closable
  // tag next to the tags of the finished filters: ticking a box updates the
  // bar immediately this way, without running the search (which would
  // reload the page before a second box could be ticked). It turns into a
  // normal tag once the filter is finished - see applyMultiValue()/submit().
  const pendingTag = activeField?.multi && liveToken.isFilter && multiSelection.length > 0 ? (
    <Tag
      data-cy="search-query-bar-pending-tag"
      style={{
        marginInlineEnd: 4,
        fontSize: 14,
        padding: '3px 9px',
        lineHeight: '18px',
        borderStyle: 'dashed',
        background: '#fafafa',
      }}
    >
      {hasExcludePrefix(draft) && <span style={{ color: '#cf1322' }}>-</span>}
      <span>{activeField.key}</span>
      <span style={{ color: '#aaa' }}>:</span>
      <span
        style={{ color: '#1677ff', fontWeight: 500 }}
        title={multiSelection.map(o => o.label).join(' OR ')}
      >
        {formatGroupValues(multiSelection.map(o => o.label))}
      </span>
    </Tag>
  ) : null

  const draftInput = (
    <input
      ref={inputRef}
      type="text"
      data-cy="search-query-bar-input"
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
              data-cy="search-query-bar-tag"
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
                  <span
                    style={{ color: '#1677ff', fontWeight: 500 }}
                    title={isGroupValue(parsed.value) ? parseGroupValues(parsed.value).join(' OR ') : undefined}
                  >
                    {isGroupValue(parsed.value) ? formatGroupValues(parseGroupValues(parsed.value)) : unquote(parsed.value)}
                  </span>
                </>
              ) : (
                <span>{unquote(raw)}</span>
              )}
            </Tag>
          )
        })}
        {pendingTag}
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
        <Tooltip
          title={helpContent}
          styles={{ root: { maxWidth: 360 } }}
          // Keeps the tooltip open while the pointer travels onto it, so
          // the text stays readable long enough to actually be read.
          mouseLeaveDelay={0.2}
        >
          <i
            className="fa fa-question-circle-o"
            role="button"
            tabIndex={0}
            aria-label={t('SEARCH_QUERY_BAR_HELP')}
            style={{ color: '#aaa', cursor: 'help', fontSize: 15, marginLeft: 10 }}
            onClick={(e) => e.stopPropagation()}
          />
        </Tooltip>
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
          <Segmented
            size="small"
            block
            value={effectiveDateMode}
            // Hand focus back to the draft input, so the bar keeps behaving
            // normally (typing, blur-to-commit) after flipping the mode.
            onChange={setDateMode}
            options={[
              { value: 'day', label: t('SEARCH_QUERY_BAR_DATE_DAY') },
              { value: 'range', label: t('SEARCH_QUERY_BAR_DATE_RANGE') },
            ]}
            style={{ marginBottom: 8 }}
          />
          {effectiveDateMode === 'range' ? (
            <DatePicker.RangePicker
              ref={datePickerRef}
              open
              format={datePickerProps.format}
              value={dateRangeDraft ?? editedDateRange}
              onCalendarChange={setDateRangeDraft}
              onChange={applyDateRange}
              getPopupContainer={(trigger) => trigger.parentElement}
            />
          ) : (
            <DatePicker
              ref={datePickerRef}
              open
              format={datePickerProps.format}
              value={liveToken.value && DATE_VALUE_RE.test(liveToken.value) ? dayjs(liveToken.value) : null}
              onChange={applyDate}
              getPopupContainer={(trigger) => trigger.parentElement}
            />
          )}
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
              data-cy="search-query-bar-suggestion"
              onMouseDown={(e) => {
                e.preventDefault()
                if (suggestion.type === 'checkbox') {
                  // Anywhere but the checkbox itself (which stops this
                  // handler) means "this value, done" - see applyMultiValue().
                  applyMultiValue(suggestion)
                } else {
                  applySuggestion(suggestion)
                }
              }}
              onMouseEnter={() => setHighlightedIndex(index)}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                padding: '6px 12px',
                cursor: 'pointer',
                background: index === highlightedIndex ? '#f5f5f5' : 'transparent',
              }}
            >
              <div data-cy="search-query-bar-suggestion-label" style={{
                flex: 1,
                minWidth: 0,
                // Previews the exclusion while its shortcut is hovered.
                textDecoration: index === excludeHoverIndex ? 'line-through' : 'none',
              }}>
                {suggestion.type === 'key' ? (
                  <><strong>{suggestion.key.slice(4)}</strong><span style={{ color: '#aaa' }}> — {suggestion.label}</span></>
                ) : suggestion.type === 'checkbox' ? (
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    {/* Not a <label>: the checkbox and the text do different
                        things here - ticking adds a value and keeps the
                        dropdown open, the text finishes the filter. */}
                    <span
                      onMouseDown={(e) => {
                        e.preventDefault()
                        e.stopPropagation()
                        toggleMultiValue(suggestion)
                        setHighlightedIndex(index)
                      }}
                      style={{ display: 'inline-flex' }}
                    >
                      <Checkbox checked={suggestion.checked} onChange={() => {}} />
                    </span>
                    <span>{suggestion.label}</span>
                  </div>
                ) : (
                  <>
                    <div>{suggestion.label}</div>
                    {suggestion.description && (
                      // Context that tells otherwise-alike values apart, e.g.
                      // an order number's date/owner/customer.
                      <div style={{ fontSize: 12, color: '#aaa', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {suggestion.description}
                      </div>
                    )}
                  </>
                )}
              </div>
              {index === highlightedIndex && (
                <span
                  role="button"
                  data-cy="search-query-bar-exclude"
                  onMouseEnter={() => setExcludeHoverIndex(index)}
                  onMouseLeave={() => setExcludeHoverIndex(null)}
                  onMouseDown={(e) => {
                    // Stops the row's own handler from *including* the
                    // suggestion instead, and keeps focus in the draft input.
                    e.preventDefault()
                    e.stopPropagation()
                    applyExclusion(suggestion)
                  }}
                  style={{
                    flexShrink: 0,
                    fontSize: 12,
                    color: index === excludeHoverIndex ? '#595959' : '#aaa',
                  }}
                >
                  {t('SEARCH_QUERY_BAR_EXCLUDE')}
                </span>
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
