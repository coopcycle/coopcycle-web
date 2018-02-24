import TaskRangePicker from '../widgets/TaskRangePicker'
import _ from 'lodash'

let tags = []
const popoverTemplate = document.querySelector('#task-tag-list-popover-template').textContent

const tagsAsArray = formName => {
  const tagsAsString = $(`#${formName}_tagsAsString`).val()
  const slugs = _.without(tagsAsString.split(' '), '')

  let tagsArray = []
  if (slugs.length > 0) {
    tagsArray = slugs.map(slug => findBySlug(tags, slug))
  }

  return tagsArray
}

const containsTag = (formName, tag) => !!findBySlug(tagsAsArray(formName), tag.slug)

const addTagLabel = (formName, tag) => {
  $(`form[name="${formName}"] .task-tag-list`).append(tagTemplate(tag))
}

const addTag = (formName, tag) => {
  addTagLabel(formName, tag)
  const tagsAsString = $(`#${formName}_tagsAsString`).val()
  $(`#${formName}_tagsAsString`).val(`${tagsAsString} ${tag.slug}`)
}

const removeTag = (formName, tag) => {

  const $tagList = $(`form[name="${formName}"] .task-tag-list`)

  $tagList.find(`[data-slug="${tag.slug}"]`).remove()

  let slugs = []
  $tagList.find('[data-slug]').each(function(el) {
    slugs.push($(this).data('slug'))
  })
  $(`#${formName}_tagsAsString`).val(slugs.join(' '))
}

const toggleTag = (formName, tag) => {
  containsTag(formName, tag) ? removeTag(formName, tag) : addTag(formName, tag)
}

const findBySlug = (tags, slug) => {
  return _.find(tags, tag => tag.slug === slug)
}

const tagTemplate = tag => {
  return `<span data-slug="${tag.slug}" class="label label-default" style="background-color: ${tag.color};">${tag.name}</span>`
}

const tagSelectorTemplate = tags => {
  return `
  <div class="list-group nomargin">
  ${tags.map(tag => `
    <a href="#" class="list-group-item task-tags-tag" data-name="${tag.name}" data-slug="${tag.slug}" data-color="${tag.color}">
      ${tagTemplate(tag)}
    </a>
  `).join('')}
  </div>
`
}

const initTagSelector = (formName) => {

  const originalTags = tagsAsArray(formName)
  originalTags.forEach(tag => addTagLabel(formName, tag))

  const $tagSelector = $(`form[name="${formName}"] .task-tags-selector`)
  $tagSelector.popover({
    container: 'body',
    html: true,
    placement: 'left',
    content: tagSelectorTemplate(tags),
    template: popoverTemplate
  })
  $tagSelector.closest('.modal').on('hide.bs.modal', (e) => $tagSelector.popover('hide'))
}

window.CoopCycle = window.CoopCycle || {}
window.CoopCycle.TaskModal = (formName, tagsURL) => {

  new CoopCycle.AddressInput(document.querySelector(`#${formName}_address_streetAddress`), {
    elements: {
      latitude: document.querySelector(`#${formName}_address_latitude`),
      longitude: document.querySelector(`#${formName}_address_longitude`),
      postalCode: document.querySelector(`#${formName}_address_postalCode`),
      addressLocality: document.querySelector(`#${formName}_address_addressLocality`)
    }
  })

  new TaskRangePicker(document.querySelector(`#${formName}_rangepicker`), [
    document.querySelector(`#${formName}_doneAfter`),
    document.querySelector(`#${formName}_doneBefore`)
  ])

  const timelineEl = document.querySelector(`form[name="${formName}"] ul[data-render="timeline"]`)
  if (timelineEl) {
    new CoopCycle.Timeline(timelineEl)
  }

  if (tags.length === 0) {
    fetch(tagsURL, { credentials: 'include' }).then(res => {
      res.json()
        .then(data => tags = data)
        .then(() => initTagSelector(formName))
    })
  } else {
    initTagSelector(formName)
  }

  $(document).off('click', '.task-tags-tag').on('click', '.task-tags-tag', function(e) {
    e.preventDefault()
    const $target = $(e.currentTarget)
    const tag = $target.data()
    toggleTag(formName, tag)
  })

}
