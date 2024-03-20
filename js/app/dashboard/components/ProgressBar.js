import React from 'react';

const ProgressBar = ({ segments, width, height, backgroundColor }) => {

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
    <div style={{
      backgroundColor, width, height,
      overflow: 'hidden', position: 'relative',
      borderRadius: '4px',
    }}>
        {adjustedSegments.map((segment, index) => (
          <div
            key={index}
            style={{
              backgroundColor: segment.color,
              height,
              position: 'absolute',
              width: segment.value,
              left: `${segment.offset}%`,
              top: 0,
            }}
          ></div>
        ))}
    </div>
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

