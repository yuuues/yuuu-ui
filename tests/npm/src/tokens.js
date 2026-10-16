// The verifier's case: the tokens only, the app's own rules on top.
import '@yuuues/yuuu-ui/tokens.css';
import './app.css';
import { h, render } from 'preact';

render(h('p', { class: 'check-panel' }, 'tokens'), document.getElementById('app'));
