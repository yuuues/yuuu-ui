// The full base stylesheet: tokens and classes, the app's layer on top.
import '@yuuues/yuuu-ui/base.css';
import './app.css';
import { h, render } from 'preact';

render(h('button', { class: 'btn btn--primary', type: 'button' }, 'base'), document.getElementById('app'));
