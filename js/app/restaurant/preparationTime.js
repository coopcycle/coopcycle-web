import Sortable from 'sortablejs'

import './preparationTime.scss'

const preparationTimeRules = $('#preparation_time_rules_preparationTimeRules')

const onListChange = () => {
  preparationTimeRules.children().each((index, el) => {
    $(el).find('input[type="hidden"]').val(index)
  })
}

$(document).on('click', '#preparation_time_rules_preparationTimeRules > div .close', function(e) {
  e.preventDefault()
  $(e.target).closest('.preparation_time_rules_preparationTimeRule').remove()
  onListChange()
})

$('#add-rule').on('click', (e) => {

  e.preventDefault()

  let html = preparationTimeRules
    .attr('data-prototype')
    .replace(/__name__/g, preparationTimeRules.children().length)

  preparationTimeRules.append(html)

  onListChange()
})

new Sortable(preparationTimeRules.get(0), {
  group: 'rules',
  animation: 250,
  onUpdate: onListChange,
})

onListChange()
