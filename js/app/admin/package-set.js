import React, { useState } from 'react'
import { createRoot } from 'react-dom/client'
import TagsSelect from '../components/TagsSelect'
import SwatchesPicker from 'react-color/lib/Swatches'

var $packagesList = $('#package_set_packages')

document.querySelector('form[name=package_set]').addEventListener('keydown', (event) => {
  if(event.keyCode == 13) {
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
      tags={ tags }
      defaultValue={ defaultValue }
      onChange={ tags => {
          const slugs = tags.map(tag => tag.slug)
          tagSelectEl.value = slugs.join(' ')
      }}
  />)
}

const PackageColorPicker = ({initialColorInput}) => {

  const [expanded, setExpanded] = useState(false)
  const [color, setColor] = useState(initialColorInput.value)

  return (
    <>
      <a
        onClick={() => setExpanded(true)}
        style={{
          display: "block",
          width: "20px",
          height: "20px",
          backgroundColor: color,
          border: "1px solid black"
        }}
      ></a>
      { expanded ?
        <div style={{position: 'absolute', right: 0, zIndex: 1}}>
          <SwatchesPicker // I chose a color picker with just click selection so the user select by click and we simply close it
            color={ color }
            onChange={ color => {
              if (color.hex.length === 7) {
                setExpanded(false)
                setColor(color.hex)
                initialColorInput.value = color.hex
              }
            }}
          />
        </div>
        : null
      }
    </>
  )
}

const mountColorPicker = (packageWidgetEl) => {

  const colorInput = packageWidgetEl.querySelector(".package_color")
  colorInput.classList.add('d-none')

  const colorPickerWidget = document.createElement('div')
  colorInput.closest('.form-group').appendChild(colorPickerWidget)
  createRoot(colorPickerWidget).render(<PackageColorPicker initialColorInput={colorInput} />)
}

const bindDelete = (packageWidgetEl) => {
  const deleteEl = packageWidgetEl.querySelector('.delete-package-entry')
  deleteEl.addEventListener('click', function () {
    var parent = $(packageWidgetEl)
    var counter = $packagesList.data('widget-counter') || $packagesList.children().length
    counter--
    $packagesList.data('widget-counter', counter)
    parent.remove()
  })
}

const initJSBindingsForPackageEntry = (packageWidgetEl) => {
  mountTagsSelect(packageWidgetEl)
  mountColorPicker(packageWidgetEl)
  bindDelete(packageWidgetEl)
}

document.querySelectorAll(".package-entry").forEach((packageWidgetEl) => {
  initJSBindingsForPackageEntry(packageWidgetEl)
})

document.querySelector('#package_set_packages_add').addEventListener('click', function () {
  var counter = $packagesList.data('widget-counter') || $packagesList.children().length
  var newWidget = $packagesList.attr('data-prototype')
  newWidget = newWidget.replace(/__name__/g, counter)
  counter++
  $packagesList.data('widget-counter', counter)
  var $newElem = $(newWidget)
  $newElem.appendTo($packagesList)

  initJSBindingsForPackageEntry($newElem.get(0))
})
