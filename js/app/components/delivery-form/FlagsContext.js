import { createContext } from 'react'

const FlagsContext = createContext({
  isDispatcher: false,
  isDebugPricing: false,
  isPriceBreakdownEnabled: false,
})

export default FlagsContext
