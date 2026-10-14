import mountAutoSubmitSwitch from './components/auto-submit-switch'

import '../bootstrap-reset.scss'

const FREE_DELIVERY = 'delivery_percentage_discount'
const PERCENTAGE = 'order_percentage_discount'

function fieldGroup(entry, suffix) {
  const el = entry.querySelector(`[id$="${suffix}"]`)

  return el ? el.closest('.form-group') : null
}

/**
 * Free delivery is a flat 100% off and has no configurable value, so both
 * amount and percentage stay hidden for it.
 */
function toggleRewardValueFields(entry) {
  const select = entry.querySelector('[id$="rewardType"]')

  if (!select) {
    return
  }

  const amountGroup = fieldGroup(entry, 'rewardAmount')
  const percentageGroup = fieldGroup(entry, 'rewardPercentage')

  if (amountGroup) {
    amountGroup.style.display =
      (select.value === FREE_DELIVERY || select.value === PERCENTAGE) ? 'none' : ''
  }
  if (percentageGroup) {
    percentageGroup.style.display = select.value === PERCENTAGE ? '' : 'none'
  }
}

function initRewardEntry(entry) {
  toggleRewardValueFields(entry)

  const select = entry.querySelector('[id$="rewardType"]')
  if (select) {
    select.addEventListener('change', () => toggleRewardValueFields(entry))
  }

  const deleteButton = entry.querySelector('.delete-reward-entry')
  if (deleteButton) {
    // Removing the fields is what deletes the reward: the controller treats
    // anything missing from the submitted collection as removed.
    deleteButton.addEventListener('click', () => entry.remove())
  }
}

$(function() {
  mountAutoSubmitSwitch('#loyalty_program_active_form_active')

  const list = document.getElementById('loyalty-rewards-list')

  if (!list) {
    return
  }

  list.querySelectorAll('.loyalty-reward-entry').forEach(initRewardEntry)

  document.getElementById('loyalty-rewards-add').addEventListener('click', () => {
    const index = list.dataset.index || list.children.length
    list.dataset.index = parseInt(index, 10) + 1

    const entry = document.createElement('div')
    entry.className = 'loyalty-reward-entry panel panel-default'
    entry.innerHTML =
      '<div class="panel-body">' +
      list.dataset.prototype.replace(/__name__/g, index) +
      '<button type="button" class="btn btn-sm btn-danger delete-reward-entry">' +
      '<i class="fa fa-trash"></i></button>' +
      '</div>'

    list.appendChild(entry)
    initRewardEntry(entry)
  })
})
