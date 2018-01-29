import React, { Component } from 'react'
import Autosuggest from 'react-autosuggest';

// ------------------ Autosuggest ------------------


const getSuggestionValue = suggestion => suggestion.name,
      renderSuggestion = suggestion => (<span>{suggestion.name}</span>),
      theme = {
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
    super(props);

    this.state = {
      value: '',
      suggestions: []
    };
  }

  onChange(event, { newValue }) {
    this.setState({
      value: newValue
    });
  };

  shouldRenderSuggestions(value) {
    return value.trim().length >= 3;
  }

  onSuggestionsFetchRequested({ value }) {
    $.getJSON(this.props.baseURL, { q: value }, data => {
      this.setState({
        suggestions: data
      });
    });
  };

  onSuggestionSelected(event, { suggestion, suggestionValue, suggestionIndex, sectionIndex, method }) {
    this.props.onSuggestionSelected(suggestion);
    this.setState({
      suggestions: []
    });
  }

  onSuggestionsClearRequested() {
    this.setState({
      suggestions: []
    });
  };

  render() {
    const { value, suggestions } = this.state;

    const inputProps = {
      placeholder: this.props.placeholder,
      value,
      onChange: this.onChange.bind(this),
      className: 'form-control'
    };

    return (
      <Autosuggest
        theme={theme}
        suggestions={suggestions}
        onSuggestionsFetchRequested={this.onSuggestionsFetchRequested.bind(this)}
        onSuggestionsClearRequested={this.onSuggestionsClearRequested.bind(this)}
        onSuggestionSelected={this.onSuggestionSelected.bind(this)}
        getSuggestionValue={getSuggestionValue}
        renderSuggestion={renderSuggestion}
        shouldRenderSuggestions={this.shouldRenderSuggestions.bind(this)}
        inputProps={inputProps}
      />
    );
  }
}
