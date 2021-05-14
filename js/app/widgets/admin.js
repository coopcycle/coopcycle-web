import Changelog from './Changelog'
import ColorPicker from './ColorPicker'
import ConfirmDelete from './ConfirmDelete'
import Dropzone from './Dropzone'
import OpeningHoursInput from './OpeningHoursInput'
import Switch from './Switch'

import 'prismjs'
import 'prismjs/plugins/toolbar/prism-toolbar'
import 'prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard'

import 'prismjs/themes/prism.css'
import 'prismjs/plugins/toolbar/prism-toolbar.css'
import './admin.scss'

window.CoopCycle = window.CoopCycle || {}

window.CoopCycle.Changelog = Changelog
window.CoopCycle.ColorPicker = ColorPicker
window.CoopCycle.ConfirmDelete = ConfirmDelete
window.CoopCycle.Dropzone = Dropzone
window.CoopCycle.OpeningHoursInput = OpeningHoursInput
window.CoopCycle.Switch = Switch
