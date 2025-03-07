import {
  
} from './actions'
import {
  tourAdapter
} from './adapters'

const initialState = tourAdapter.getInitialState()

export default (state = initialState) => {

  return state
}
