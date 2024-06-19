export function enableBtn(btn) {
  btn.disabled = false
  btn.removeAttribute('disabled')
}

export function disableBtn(btn) {
  btn.setAttribute('disabled', true)
  btn.disabled = true
}
