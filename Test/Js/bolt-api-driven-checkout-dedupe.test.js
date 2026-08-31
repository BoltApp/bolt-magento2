/**
 * Regression test for GH #5: Salesforce CRM / Cart2Quote payment-only checkout
 * stuck in an infinite loading state with a flood of console errors.
 *
 * Root cause: Magento carts built server-side (CRM / Cart2Quote) emit customer-data
 * updates with no `data_id` and no normalized `boltCartHints.prefill`. With no
 * `data_id`, magentoCartDataListener's timestamp de-dupe can never short-circuit
 * (see #1801). When the incoming hints also lack a `prefill` key, the merged hints
 * accumulator (`{prefill: undefined, ...}`) is never `_.isEqual` to the raw hints,
 * so isBoltCheckoutConfigureCallRequired flips true on EVERY emission -> Bolt is
 * re-configured in an infinite loop.
 *
 * The fix adds a content-signature de-dupe for emissions that carry no `data_id`.
 *
 * Runnable with plain node (no framework): `node Test/Js/bolt-api-driven-checkout-dedupe.test.js`
 * Lives under Test/ so it is excluded from the browser-JS eslint pass.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const SOURCE = path.join(__dirname, '..', '..', 'view', 'frontend', 'web', 'js', 'bolt-api-driven-checkout.js');

// Faithful-enough deep equal for plain hint objects: like lodash, `{a: undefined}`
// is NOT equal to `{}` (the key exists), which is what makes the loop reproduce.
function deepEqual(a, b) {
    if (a === b) { return true; }
    if (typeof a !== 'object' || typeof b !== 'object' || a === null || b === null) { return a === b; }
    if (Array.isArray(a) !== Array.isArray(b)) { return false; }
    const ka = Object.keys(a);
    const kb = Object.keys(b);
    if (ka.length !== kb.length) { return false; }
    return ka.every((k) => Object.prototype.hasOwnProperty.call(b, k) && deepEqual(a[k], b[k]));
}

function loadCore() {
    const _ = { isEqual: deepEqual };
    const $ = { isEmptyObject: (o) => !o || Object.keys(o).length === 0 };
    const customerData = { get: () => (() => undefined), invalidate() {}, reload() {} };
    const whenDefined = function () {};
    const authenticationPopup = {};
    const window = {
        boltConfig: {
            additional_checkout_button_class: '',
            additional_checkout_button_attributes: '',
            button_css_styles: '',
            trackCallbacks: {},
            selectors: []
        },
        MutationObserver: function () {}
    };
    let entry;
    const define = (deps, factory) => { entry = factory($, _, customerData, whenDefined, authenticationPopup); };
    const runner = new Function('define', 'window', 'require', fs.readFileSync(SOURCE, 'utf8'));
    runner(define, window, () => {});

    const core = entry.BoltCheckoutApiDriven;
    core.readyStatusBarrier = core.initBarrier();
    core.cartBarrier = core.initBarrier();
    core.hintsBarrier = core.initBarrier();
    core.customerCart = null; // resolveReadyStatusPromise() early-returns
    core._configureCalls = 0;
    core.boltCheckoutConfigureCall = function () { core._configureCalls++; };
    return core;
}

function emit(core, times, cart) {
    for (let i = 0; i < times; i++) {
        core.magentoCartDataListener(JSON.parse(JSON.stringify(cart)));
    }
}

// The reproducer: CRM/Cart2Quote cart -> no data_id, hints without a prefill key.
let core = loadCore();
emit(core, 5, { quoteMaskedId: 'q1', boltCartHints: { metadata: { items: 1 } } });
assert.strictEqual(core._configureCalls, 1,
    'CRM cart (no data_id, no prefill) must configure Bolt exactly once for identical emissions, got ' + core._configureCalls);

// A genuine content change must still reconfigure (preserves #1801 intent).
core = loadCore();
emit(core, 1, { quoteMaskedId: 'q1', boltCartHints: { metadata: { items: 1 } } });
emit(core, 1, { quoteMaskedId: 'q2', boltCartHints: { metadata: { items: 2 } } });
assert.strictEqual(core._configureCalls, 2,
    'A changed CRM cart must trigger a reconfigure, got ' + core._configureCalls);

// Normal carts (with data_id) still de-dupe on the timestamp.
core = loadCore();
emit(core, 5, { data_id: 111, quoteMaskedId: 'q1', boltCartHints: { prefill: { email: 'a@b.c' } } });
assert.strictEqual(core._configureCalls, 1,
    'Normal cart with a stable data_id must configure once, got ' + core._configureCalls);

console.log('bolt-api-driven-checkout dedupe: all assertions passed');
