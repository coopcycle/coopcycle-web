import { TaskPayload, ManualSupplementValues } from '../../api/types';

export type OrderFormValues = {
  manualSupplements: ManualSupplementValues[];
  isSavedOrder?: boolean;
};

export type DeliveryFormValues = {
  tasks: TaskPayload[];
  rrule?: string;
  order: OrderFormValues;
  variantIncVATPrice?: number;
  variantName?: string;
};

export type FlagsContextType = {
  isDispatcher: boolean;
  isDebugPricing: boolean;
  isPriceBreakdownEnabled: boolean;
};

export type PriceValues = {
  VAT: number;
  exVAT: number;
};
