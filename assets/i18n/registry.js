/**
 * The one place a bundle's JavaScript asks for a translation.
 *
 * ## Why a registry and not a direct import
 *
 * `symfony/ux-translator` dumps the catalogue to `var/translations/index.js` and the application
 * wires it up in `assets/translator.js`. Both paths belong to the *project*. A Stimulus
 * controller shipped inside `vendor/jul6art/<bundle>/assets` cannot import either of them: the
 * relative path out of `vendor/` does not exist, and hard-coding one would tie every bundle to
 * one application's layout.
 *
 * So the application hands its translator over, once, at boot:
 *
 * ```js
 * // assets/app.js
 * import { trans } from './translator';
 * import { registerTranslator } from '@jul6art/core-bundle/i18n/registry';
 *
 * // The domain is fixed HERE, once. `ux_translator.domains: javascript` restricts what is
 * // dumped; it does NOT change the default domain of `trans()`, which stays `messages`.
 * registerTranslator((key, parameters) => trans(key, parameters, 'javascript'));
 * ```
 *
 * and every bundle reads through `t()` without knowing any of that.
 */

/** @type {((key: string, parameters: Record<string, string|number>) => string) | null} */
let translator = null;

let warned = false;

/**
 * Installs the application's translator. Call it once, from the entry point, before any
 * controller connects.
 *
 * @param {(key: string, parameters: Record<string, string|number>) => string} fn
 * @throws {TypeError} when handed something that is not callable — a miswired boot must be loud,
 *                     not silently degrade every label of the application to a raw key
 */
export function registerTranslator(fn) {
    if (typeof fn !== 'function') {
        throw new TypeError('registerTranslator() expects a function, got ' + typeof fn + '.');
    }

    translator = fn;
    warned = false;
}

/** Is a translator installed? Mostly useful to a test, or to a fallback path. */
export function hasTranslator() {
    return translator !== null;
}

/**
 * Translates a key, returning the key itself when nothing can translate it.
 *
 * ⚠️ Returning the key is deliberate — it is what the previous mixin did, and what
 * `ux-translator` does — so a half-finished migration degrades to a visible key rather than to a
 * blank screen. But silence is exactly what let `bulk.select_all` reach production as an
 * aria-label, so a missing registration says so in the console. Once, not per call: a datatable
 * calls this hundreds of times per draw.
 *
 * @param {string} key
 * @param {Record<string, string|number>} [parameters]
 * @returns {string}
 */
export function t(key, parameters = {}) {
    if (translator === null) {
        if (!warned) {
            warned = true;
            // eslint-disable-next-line no-console
            console.warn(
                '[jul6art] No translator registered: every label falls back to its key. ' +
                    'Call registerTranslator() from the application entry point.'
            );
        }

        return key;
    }

    return translator(key, parameters);
}

/**
 * Drops the registered translator. For tests: nothing in an application should ever need it.
 */
export function resetTranslator() {
    translator = null;
    warned = false;
}
