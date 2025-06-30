import React, { useState } from 'react'
import { createRoot } from 'react-dom/client'
import TagsSelect from '../components/TagsSelect'

var $packagesList = $('#package_set_packages')

document.querySelector('form[name=package_set]').addEventListener('keydown', (event) => {
  if (event.keyCode == 13) {
    event.preventDefault()
    return false
  }
})

const mountTagsSelect = (packageWidgetEl) => {

  const tagSelectEl = packageWidgetEl.querySelector(".package_tags")
  tagSelectEl.classList.add('d-none')

  const tags = JSON.parse(tagSelectEl.dataset.tags)
  const defaultValue = tagSelectEl.value

  const newTagSelectElement = document.createElement('div')
  tagSelectEl.closest('.form-group').appendChild(newTagSelectElement)
  createRoot(newTagSelectElement).render(
    <TagsSelect
      tags={tags}
      defaultValue={defaultValue}
      onChange={tags => {
        const slugs = tags.map(tag => tag.slug)
        tagSelectEl.value = slugs.join(' ')
      }}
    />)
}

const bindDelete = (packageWidgetEl) => {
  const deleteEl = packageWidgetEl.querySelector('.delete-package-entry')
  deleteEl.addEventListener('click', function() {
    var parent = $(packageWidgetEl)
    var counter = $packagesList.data('widget-counter') || $packagesList.children().length
    counter--
    $packagesList.data('widget-counter', counter)
    parent.remove()
  })
}

const initJSBindingsForPackageEntry = (packageWidgetEl) => {
  mountTagsSelect(packageWidgetEl)
  new CoopCycle.ColorPicker(packageWidgetEl.querySelector(".package_color"))
  bindDelete(packageWidgetEl)
}

document.querySelectorAll(".package-entry").forEach((packageWidgetEl) => {
  initJSBindingsForPackageEntry(packageWidgetEl)
})

document.querySelector('#package_set_packages_add').addEventListener('click', function() {
  var counter = $packagesList.data('widget-counter') || $packagesList.children().length
  var newWidget = $packagesList.attr('data-prototype')
  newWidget = newWidget.replace(/__name__/g, counter)
  counter++
  $packagesList.data('widget-counter', counter)
  var $newElem = $(newWidget)
  $newElem.appendTo($packagesList)

  initJSBindingsForPackageEntry($newElem.get(0))
})
