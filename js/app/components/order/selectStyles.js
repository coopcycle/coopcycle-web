// react-select renders its control & menu with a plain white background,
// regardless of the app's color scheme. Left unstyled, its text picks up
// inherited color from the page (e.g. the near-white text color the
// DaisyUI dark theme sets on :root when the OS is in dark mode), which
// makes the options unreadable on their white background.
// These explicit colors keep the widget readable in both light and dark mode.
// @see https://react-select.com/styles
export const selectStyles = {
  control: base => ({
    ...base,
    backgroundColor: '#fff',
    color: '#333',
  }),
  singleValue: base => ({
    ...base,
    color: '#333',
  }),
  input: base => ({
    ...base,
    color: '#333',
  }),
  menu: base => ({
    ...base,
    backgroundColor: '#fff',
  }),
  option: (base, state) => ({
    ...base,
    color: state.isSelected ? '#fff' : '#333',
    backgroundColor: state.isSelected
      ? base.backgroundColor
      : state.isFocused
        ? '#f5f5f5'
        : '#fff',
  }),
}
