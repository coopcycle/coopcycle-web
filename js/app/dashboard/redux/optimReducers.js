import { setOptimResult } from "./actions"

const initialState = {}

export default (state = initialState, action) => {
  switch (action.type) {
    case setOptimResult.type:
      return {
        ...state,
        lastOptimResult: action.payload,
      }
    default:
      return state
  }
}