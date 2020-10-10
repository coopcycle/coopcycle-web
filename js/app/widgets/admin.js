import ColorPicker from './ColorPicker'
import ConfirmDelete from './ConfirmDelete'
import Dropzone from './Dropzone'
import MonthPicker from './MonthPicker'
import Switch from './Switch'

import 'prismjs'
import 'prismjs/plugins/toolbar/prism-toolbar'
import 'prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard'

import 'prismjs/themes/prism.css'
import 'prismjs/plugins/toolbar/prism-toolbar.css'
import './admin.scss'

window.CoopCycle = window.CoopCycle || {}

window.CoopCycle.ColorPicker = ColorPicker
window.CoopCycle.ConfirmDelete = ConfirmDelete
window.CoopCycle.Dropzone = Dropzone
window.CoopCycle.MonthPicker = MonthPicker
window.CoopCycle.Switch = Switch
