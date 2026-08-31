// The Polski menu icon sat 7px low until hovered. WordPress core styles the
// same pseudo-element with `padding: 7px 0` (wp-admin/css/admin-menu.css,
// `div.wp-menu-image:before`), the base rule never reset it, and only the hover
// rule did, which is exactly what "wrong until you point at it" looks like.
//
// Run: node tests/admin-menu-icon-check.mjs
import { readFileSync } from 'node:fs';
import assert from 'node:assert';

const css = readFileSync(new URL('../assets/css/admin-menu-icon.css', import.meta.url), 'utf8');

const rule = (selectorFragment) => {
	const i = css.indexOf(selectorFragment);
	assert.ok(i >= 0, `selector not found: ${selectorFragment}`);
	const open = css.indexOf('{', i);
	return css.slice(open + 1, css.indexOf('}', open));
};

const base = rule('#adminmenu .toplevel_page_polski .wp-menu-image::before');
const hover = rule('#adminmenu .toplevel_page_polski:hover .wp-menu-image::before');

assert.match(base, /(^|[;{\s])padding:\s*0\s*(!important)?\s*;/, 'the base rule must zero the padding core adds, or the icon renders low');
assert.match(base, /height:\s*34px/, 'the box must fill the 34px menu-image row so the monogram centres in it');
assert.match(css, /\.folded #adminmenu[^{]*\{[^}]*height:\s*30px/, 'the folded menu uses a 30px row, so the icon needs its own height there');
assert.match(css, /max-width:\s*960px[\s\S]*?height:\s*30px/, 'below 960px WordPress folds the menu to a 30px row and the icon must follow');
assert.match(base, /background-position:\s*center center/, 'centring is what makes the height work');
assert.doesNotMatch(hover, /padding/, 'padding belongs in the base rule; leaving it only on hover is the bug this checks for');

console.log('ok   base rule zeroes the padding WordPress adds');
console.log('ok   hover rule no longer carries the only reset');
console.log('admin menu icon checks passed');
