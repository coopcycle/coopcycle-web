import { render } from '../utils/react'
import CrashTestPage from './CrashTestPage'

const container = document.getElementById('crash-test-page')
if (container) {
  render(<CrashTestPage />, container)
}
