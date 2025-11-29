import {
  FixedPrice,
  PercentagePrice,
  Price,
  PriceRange,
} from '../../delivery/pricing/pricing-rule-parser';

export function getPriceValue(price: Price): number {
  if (price instanceof FixedPrice) {
    return parseFloat(price.value) / 100 || 0;
  } else if (price instanceof PercentagePrice) {
    // 10000 = 100.00%
    return (price.percentage || 10000) / 100 - 100;
  } else if (price instanceof PriceRange) {
    return price.price / 100;
  } else {
    //  price instanceof PricePerPackage:
    //   return price.unitPrice / 100
    return 0;
  }
}
