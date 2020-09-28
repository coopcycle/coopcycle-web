import '../utils/multi-select'

const checkboxes = Array.from(document.querySelectorAll('[data-multi-select]'))
const on = document.querySelector('[data-multi-select-handle-on]')
const off = document.querySelector('[data-multi-select-handle-off]')
const offChk = document.querySelector('[data-multi-select-handle-off] input[type="checkbox"]')
const onChk = document.querySelector('[data-multi-select-handle-on] input[type="checkbox"]')

function scan() {
    const selected = checkboxes.filter(o => o.checked)

    if (selected.length > 0) {
        on.classList.remove('d-none')
        off.classList.add('d-none')
    } else {
        off.classList.remove('d-none')
        offChk.checked = false
        on.classList.add('d-none')
    }
}

checkboxes.forEach(c => {
    c.addEventListener('change', scan)
})

function onClickChk() {
    document.querySelectorAll('[data-multi-select]')
        .forEach(c => { c.checked = this.checked })
    scan()
}

offChk.addEventListener('click', onClickChk)
onChk.addEventListener('click', onClickChk)

$('#attach-to-org-modal').on('show.bs.modal', function () {
    $('#attach_to_organization_tasks').val(
        checkboxes
            .filter(c => c.checked)
            .map(c => c.dataset.taskId).join(',')
    )
})
