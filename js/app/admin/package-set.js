import React from 'react'
import { createRoot } from 'react-dom/client'
import TagsSelect from '../components/TagsSelect'

const mountTagsSelect = (el) => {
  el.classList.add('d-none')

  const tags = JSON.parse(el.dataset.tags)
  const defaultValue = el.value

  const tagSelectElement = document.createElement('div')
  el.closest('.form-group').appendChild(tagSelectElement)
  createRoot(tagSelectElement).render(
    <TagsSelect
      tags={ tags }
      defaultValue={ defaultValue }
      onChange={ tags => {
          const slugs = tags.map(tag => tag.slug)
          el.value = slugs.join(' ')
      }}
  />)
}

var $packagesList = $('#package_set_packages')

$('#package_set_packages_add').on('click', function () {
  var counter = $packagesList.data('widget-counter') || $packagesList.children().length
  var newWidget = $packagesList.attr('data-prototype')
  newWidget = newWidget.replace(/__name__/g, counter)
  counter++
  $packagesList.data('widget-counter', counter)
  var newElem = $(newWidget)
  newElem.appendTo($packagesList)

  mountTagsSelect(newElem.find('.package_tags').get(0))
})

$('.delete-package-entry').on('click', function (e) {
  var parent = $(e.target).closest('.package-entry')
  var counter = $packagesList.data('widget-counter') || $packagesList.children().length
  counter--
  $packagesList.data('widget-counter', counter)
  parent.remove()
})

document.querySelectorAll(".package_tags").forEach((el) => {
  mountTagsSelect(el)
})
