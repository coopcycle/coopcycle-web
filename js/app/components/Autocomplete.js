import React, { Component } from 'react'
import Autosuggest from 'react-autosuggest'
import { debounce } from 'lodash'

// ------------------ Autosuggest ------------------


const getSuggestionValue = suggestion => suggestion.name,
  renderSuggestion = suggestion => (<span>{suggestion.name}</span>),
  themeDefaults = {
    container:                'react-autosuggest__container',
    containerOpen:            'react-autosuggest__container--open',
    input:                    'react-autosuggest__input',
    inputOpen:                'react-autosuggest__input--open',
    inputFocused:             'react-autosuggest__input--focused',
    suggestionsContainer:     'react-autosuggest__suggestions-container',
    suggestionsContainerOpen: 'react-autosuggest__suggestions-container--open',
    suggestionsList:          'react-autosuggest__suggestions-list',
    suggestion:               'react-autosuggest__suggestion',
    suggestionFirst:          'react-autosuggest__suggestion--first',
    suggestionHighlighted:    'react-autosuggest__suggestion--highlighted',
    sectionContainer:         'react-autosuggest__section-container',
    sectionContainerFirst:    'react-autosuggest__section-container--first',
    sectionTitle:             'react-autosuggest__section-title'
  }

export default class extends Component {

  constructor(props) {
    super(props)

    this.state = {
      value: '',
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

  shouldRenderSuggestions(value) {
    return value.trim().length >= 3
  }

  onSuggestionsFetchRequested({ value }) {
    this.setState({ isFetching: true })
    $.getJSON(this.props.baseURL, { q: value }, data => {
      this.setState({
        suggestions: data,
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

  render() {
    const { value, suggestions, isFetching } = this.state

    const inputProps = {
      placeholder: this.props.placeholder,
      value,
      onChange: this.onChange.bind(this),
      className: 'form-control'
    }

    let theme = themeDefaults
    if (isFetching) {
      theme = {
        ...themeDefaults,
        container: 'react-autosuggest__container react-autosuggest__container--loading',
      }
    }

    return (
      <Autosuggest
        theme={theme}
        suggestions={suggestions}
        onSuggestionsFetchRequested={this.onSuggestionsFetchRequested}
        onSuggestionsClearRequested={this.onSuggestionsClearRequested.bind(this)}
        onSuggestionSelected={this.onSuggestionSelected.bind(this)}
        getSuggestionValue={getSuggestionValue}
        renderSuggestion={renderSuggestion}
        shouldRenderSuggestions={this.shouldRenderSuggestions.bind(this)}
        inputProps={inputProps}
      />
    )
  }
}
