import TaskRangePicker from '../widgets/TaskRangePicker'
import TagsInput from '../widgets/TagsInput'
import _ from 'lodash'

let tags = []

const tagsAsArray = formName => {
  const tagsAsString = $(`#${formName}_tagsAsString`).val()
  const slugs = _.without(tagsAsString.split(' '), '')

  let tagsArray = []
  if (slugs.length > 0) {
    tagsArray = slugs.map(slug => findBySlug(tags, slug))
  }

  return tagsArray
}

const findBySlug = (tags, slug) => {
  return _.find(tags, tag => tag.slug === slug)
}

const initTagSelector = (formName) => {
  new TagsInput(document.querySelector(`form[name="${formName}"] .task-tag-list`), {
    tags,
    defaultValue: tagsAsArray(formName),
    onChange: tags => {
      const slugs = _.map(tags, tag => tag.slug)
      $(`#${formName}_tagsAsString`).val(slugs.join(' '))
    }
  })
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

  new TaskRangePicker(document.querySelector(`#${formName}_dateRange_widget`), [
    document.querySelector(`#${formName}_dateRange_after`),
    document.querySelector(`#${formName}_dateRange_before`)
  ])

  const timelineEl = document.querySelector(`form[name="${formName}"] ul[data-render="timeline"]`)
  if (timelineEl) {
    new CoopCycle.Timeline(timelineEl, {
      format: 'lll',
      itemColor: item => {
        const eventName = item.getAttribute('data-event')
        switch (eventName) {
        case 'task:done':
          return 'green'
        case 'task:failed':
        case 'task:cancelled':
          return 'red'
        default:
          return 'blue'
        }
      }
    })
  }

  if (tags.length === 0) {
    fetch(tagsURL, { credentials: 'include' }).then(res => {
      res.json()
        .then(data => tags = data)
        .then(() => initTagSelector(formName))
    })
  } else {
    initTagSelector(formName)
  }

  if ($(`form[name="${formName}"]`).data('ajax') === true) {

    $(document)
      .off('click', `form[name="${formName}"] button[type="submit"]`)
      .on('click', `form[name="${formName}"] button[type="submit"]`, function(e) {
        e.preventDefault()

        const $form = $(`form[name="${formName}"]`)

        const data = $form.serializeArray()

        // We add the name of the button that was actually clicked
        data.push({
          name: $(e.target).attr('name'),
          value: ''
        })

        fetch($form.attr('action'), {
          credentials: 'include',
          method: 'POST',
          body: new URLSearchParams($.param(data)),
          headers: new Headers({
            'Accept': 'application/json',
          })
        })
          .then(res => {
            if (res.ok) {
              // HTTP 204 means a task was deleted
              // Reload the page because it's easier
              // TODO Stop reloading the page, and update Redux
              if (204 === res.status) {
                window.location.reload()
              } else {
                res.text()
                  .then(text => {
                    // Try to parse the response as JSON
                    // If the response is not is JSON format, it means the form is not valid
                    try {
                      const task = JSON.parse(text)
                      const $modal = $form.closest('.modal')
                      const event = $.Event('task.form.success', { task })
                      $modal.trigger(event)
                      $modal.modal('hide')
                    } catch(e) {
                      $form.closest('.modal-dialog').replaceWith(text)
                      $('.modal-dialog').addClass('modal--shake')
                    }
                  })
              }

            }
          })

        return false
      })

  }

}
