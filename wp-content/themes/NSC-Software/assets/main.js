import 'vite/modulepreload-polyfill'
import NscSoftwareComponent from './scripts/NSC-SoftwareComponent'

import 'lazysizes'

if (import.meta.env.DEV) {
  import('@vite/client')
}

import.meta.glob([
  '../Components/**',
  '../assets/**',
  '!**/*.js',
  '!**/*.scss',
  '!**/*.php',
  '!**/*.twig',
  '!**/screenshot.png',
  '!**/*.md'
])

window.customElements.define(
  'NSC-Software-component',
  NscSoftwareComponent
)
