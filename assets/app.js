/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

import { registerReactControllerComponents } from '@symfony/ux-react';

// start the Stimulus application
import './bootstrap.js';

registerReactControllerComponents(require.context('./react/controllers', true, /\.(j|t)sx?$/));
