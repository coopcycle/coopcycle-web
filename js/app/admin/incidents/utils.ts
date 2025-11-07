import { money as _money, weight as _weight } from '../../utils/format';

export function money(amount: number): string {
  return _money(amount);
}

export function weight(amount: number): string {
  return _weight(amount);
}
