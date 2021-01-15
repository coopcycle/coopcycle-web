import React from 'react'
import { render } from 'react-dom'
import TagsSelect from '../components/TagsSelect'

var tagsEl = document.querySelector('#store_tags');

if (tagsEl) {

  const el = document.createElement('div')
  tagsEl.closest('.form-group').appendChild(el)

  tagsEl.classList.add('d-none')

  const tags = JSON.parse(tagsEl.dataset.tags)
  const defaultValue = tagsEl.value

  render(
    <TagsSelect
      tags={ tags }
      defaultValue={ defaultValue }
      onChange={ tags => {
        const slugs = tags.map(tag => tag.slug)
        tagsEl.value = slugs.join(' ')
      } } />, el)
}

