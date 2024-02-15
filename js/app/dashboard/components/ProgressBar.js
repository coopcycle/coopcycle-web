import React from 'react';

const ProgressBar = ({ segments, width, height, backgroundColor }) => {
  const rx = height / 2;
  const maskId = `round-corner-mask-${Math.random().toString(36)}`;

  const calculateOffset = (segments) => {
    let total = 0;
    return segments.map((segment) => {
      const offset = total;
      total += parseFloat(segment.value);
      return { ...segment, offset };
    });
  };

  const adjustedSegments = calculateOffset(segments);

  return (
    <svg width={width} height={height}>
    <defs>
        <mask id={maskId}>
          <rect width="100%" height={height} fill="#fff" rx={rx} ry={rx} />
        </mask>
      </defs>
      <rect width="100%" height={height} fill={backgroundColor} rx={rx} ry={rx} />
      <g mask={`url(#${maskId})`}>
        {adjustedSegments.map((segment, index) => (
          <rect
            key={index}
            x={`${segment.offset}%`}
            width={segment.value}
            height={height}
            fill={segment.color}
          />
        ))}
      </g>
    </svg>
  );
};

export default ProgressBar;

// Example of usage:
// <ProgressBar
//   width="100%"
//   height="30"
//   backgroundColor="white"
//   segments={[
//     { value: '20%', color: 'red' },
//     { value: '30%', color: 'green' },
//     // ... add more segments if needed
//   ]}
// />

