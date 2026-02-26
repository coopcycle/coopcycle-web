import React, { Component, Children } from 'react'
import Autosuggest from 'react-autosuggest'
import { defaultTheme } from 'react-autosuggest/dist/theme'
import { debounce } from 'lodash'
import classNames from 'classnames'
import { useTranslation } from 'react-i18next'

import '../../../assets/css/react-autosuggest.scss'

// ------------------ Autosuggest ------------------


const getSuggestionValue = suggestion => suggestion.name

const renderSuggestion = suggestion => (<span>{suggestion.name}</span>)

const loadingTheme = {
  ...defaultTheme,
  container: `${defaultTheme.container} react-autosuggest__container--loading`,
}

const SuggestionsContainer = ({ containerProps, children, query, onShowMore }) => {

  const { t } = useTranslation()

  if (!query || query.length === 0) {
    return null
  }

  const hasChildren = Children.count(children)

  return (
    <div {...containerProps}>
      {children}
      <div className={ classNames(
        { 'border-top': hasChildren },
        { 'p-3': hasChildren > 0 }
      )}>
        { hasChildren ? <a href="#" onClick={ onShowMore }><small>{ t('SHOW_MORE_RESULTS', { query }) }</small></a> : null }
        { !hasChildren ? <small>{ t('PRESS_ENTER_TO_SEARCH', { query }) }</small> : null }
      </div>
    </div>
  )
}

export default class extends Component {

  constructor(props) {
    super(props)

    this.state = {
      value: props.initialValue || '',
      suggestions: [],
      isFetching: false,
    }

    this.onSuggestionsFetchRequested = debounce(
      this.onSuggestionsFetchRequested.bind(this),
      350
    )
  }

  onChange(event, { newValue }) {
    this.setState({
      value: newValue
    })
  }

  shouldRenderSuggestions(value, reason) {

    if (reason === 'render') {
      return true
    }

    return value.trim().length >= 3
  }

  onSuggestionsFetchRequested({ value }) {
    this.setState({ isFetching: true })
    $.getJSON(this.props.baseURL, { q: value }, data => {
      this.setState({
        suggestions: this.props.responseProp ? data[this.props.responseProp] : data,
        isFetching: false,
      })
    })
  }

  onSuggestionSelected(event, { suggestion }) {
    const { clearOnSelect } = this.props
    let newState = {
      suggestions: []
    }
    if (clearOnSelect) {
      newState.value = ''
    }
    this.props.onSuggestionSelected(suggestion)
    this.setState(newState)
  }

  onSuggestionsClearRequested() {
    this.setState({
      suggestions: []
    })
  }

  renderSuggestionsContainer(props) {
    return (
      <SuggestionsContainer { ...props } onShowMore={ (e) => {
        e.preventDefault()
        e.target.closest('form').submit()
      }} />
    )
  }

  render() {
    const { value, suggestions, isFetching } = this.state

    const inputProps = {
      placeholder: this.props.placeholder,
      value,
      onChange: this.onChange.bind(this),
      className: 'form-control',
      name: 'q'
    }

    let otherProps = {}
    if (this.props.searchOnEnter) {
      otherProps = {
        ...otherProps,
        renderSuggestionsContainer: this.renderSuggestionsContainer.bind(this)
      }
    }

    return (
      <Autosuggest
        theme={ isFetching ? loadingTheme : defaultTheme }
        suggestions={suggestions}
        onSuggestionsFetchRequested={this.onSuggestionsFetchRequested}
        onSuggestionsClearRequested={this.onSuggestionsClearRequested.bind(this)}
        onSuggestionSelected={this.onSuggestionSelected.bind(this)}
        getSuggestionValue={getSuggestionValue}
        renderSuggestion={ this.props.renderSuggestion || renderSuggestion }
        shouldRenderSuggestions={this.shouldRenderSuggestions.bind(this)}
        inputProps={inputProps}
        { ...otherProps }
      />
    )
  }
}
