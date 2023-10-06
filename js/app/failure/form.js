import './form.scss'

const reasonPrototype = document.querySelector('#custom-prototype').innerHTML
const reasonList = document.querySelector('#reasons-form')

document.getElementById("add_reason").addEventListener("click", function (evt) {
  evt.preventDefault()
  reasonList.insertAdjacentHTML("beforeend", reasonPrototype.replaceAll("__NAME__", reasonList.dataset.index))
  reasonList.dataset.index++
});

window.deleteReason = function(el) {
  el.parentNode.parentNode.remove()
}
