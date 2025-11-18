import { createContext } from 'react';
import type { FlagsContextType } from './types';

const FlagsContext = createContext<FlagsContextType>({
  isDebugPricing: false,
  isPriceBreakdownEnabled: false,
});

export default FlagsContext;
