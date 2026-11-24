// Tailwind's engine and yuuu-ui's theme, through src/utilities.css.
import './utilities.css';
import { h, render } from 'preact';

// The classes are written out in full: Tailwind finds them by scanning the
// source, so they must appear as literal strings.
render(
    h('div', { class: 'bg-surface text-muted rounded-lg shadow-card p-4 border border-border bg-red-500 dark:bg-bg' },
        h('button', { class: 'btn btn--primary', type: 'button' }, 'utilities')),
    document.getElementById('app'),
);
