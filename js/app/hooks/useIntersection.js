import { useState, useEffect } from 'react'

/**
 * https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API
 * https://www.webtips.dev/webtips/react-hooks/element-in-viewport
 * @param {*} element to detect when is visible in the Viewport
 * @param {*} rootMargin margin around the element to take into account
 * @returns
 */
export const useIntersection = (element, rootMargin) => {
  const [isVisible, setState] = useState(false);

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        setState(entry.isIntersecting);
      }, { rootMargin }
    );

    if (element.current) observer.observe(element.current);

    return () => {
      if (element.current) observer.unobserve(element.current);
    };
  }, []);

  return isVisible;
};
