import { createContext } from 'react';
import type { FlagsContextType } from './types';

const FlagsContext = createContext<FlagsContextType>({
  isDebugPricing: false,
  isPriceBreakdownEnabled: false,
  isReverseDeliveryEnabled: false,
});

export default FlagsContext;
