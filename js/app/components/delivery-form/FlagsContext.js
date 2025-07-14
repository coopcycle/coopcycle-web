import { createContext } from 'react'

const FlagsContext = createContext({
  isDispatcher: false,
  isDebugPricing: false,
})

export default FlagsContext
