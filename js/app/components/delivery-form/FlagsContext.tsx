import { createContext } from 'react'
import { FlagsContextType } from './types'

const FlagsContext = createContext<FlagsContextType>({
  isDispatcher: false,
  isDebugPricing: false,
  isPriceBreakdownEnabled: false,
})

export default FlagsContext
