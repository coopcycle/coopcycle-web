import { registerReactControllerComponents } from '@symfony/ux-react';

registerReactControllerComponents(require.context('.', true, /\.(j|t)sx?$/));
