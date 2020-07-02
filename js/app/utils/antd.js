import moment from 'moment'

moment.locale($('html').attr('lang'))

const longDateFormat = moment.localeData().longDateFormat('LT')

export const timePickerProps = {
  format: longDateFormat,
  // This works automatically based on "format" in ant.design 4.x,
  // but in 3.x we have to explicitly pass "use12Hours" as boolean
  // https://github.com/ant-design/ant-design/blob/9ecb12db768cd6782e82a4cf8a52958dcd164c9c/components/date-picker/generatePicker/index.tsx#L50-L52
  use12Hours: longDateFormat.includes('a') || longDateFormat.includes('A'),
}
