import { fixtureModuleMessage } from './module-helper.js';
document.querySelector('#rich-module-state').textContent = fixtureModuleMessage;
window.richFixtureModule = { message: fixtureModuleMessage, prerendering: document.prerendering };
