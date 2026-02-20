import React, { useEffect, useState, createRef, useRef } from 'react'
import { ColorPicker, Button, Flex, Tooltip, Popover, Input } from 'antd';
import chroma from 'chroma-js';

export default function ({ onChange, ...props }) {
  return (
    <ColorPicker onChange={(color, css) => {
      const colorHex = color.toHexString();
      // https://github.com/gka/chroma.js/issues/181
      const colorScheme = chroma(colorHex).get('lab.l') < 70 ? 'dark' : 'light'
      onChange(color, css, colorScheme)
    }}
    {...props} />
  )
}
