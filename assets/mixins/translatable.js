import { t as translate } from '../i18n/registry';

/**
 * Gives a Stimulus controller a `t(key, parameters)` method.
 *
 * ```js
 * import { Controller } from '@hotwired/stimulus';
 * import { translatableValues, useTranslatable } from '@jul6art/core-bundle/mixins/translatable';
 *
 * export default class extends Controller {
 *     static values = { ...translatableValues };
 *
 *     connect() {
 *         useTranslatable(this);
 *         this.element.textContent = this.t('datatable.filters');
 *     }
 * }
 * ```
 *
 * ## The two sources, and why the order is what it is
 *
 * A controller resolves a key against the `translations` value first, and only then against the
 * registry. That order is what makes a migration possible one template at a time: while a
 * template still ships `data-…-translations-value`, its tree wins; the day the attribute goes,
 * the catalogue takes over — and no controller changes in between.
 *
 * ⚠️ The attribute is a **transitional** path, not a feature. It is how libraries used to hand
 * labels to JavaScript here, it costs 8.7 kB of escaped HTML on every page that carries a
 * datatable, and the guard (`AbstractJsTranslationTestCase`) exists to fail once the last one is
 * gone. Do not reach for it in new code.
 */

/**
 * Value descriptor to spread into `static values`.
 *
 * ⚠️ Kept even though the registry needs nothing from it: sixteen controllers across the
 * ecosystem spread it, and removing it would break their `static values` at import time, in a
 * bundle, with no error anyone can read.
 */
export const translatableValues = {
    translations: { type: Object, default: {} },
};

/**
 * Attaches `t()` to a controller instance. Call it from `connect()`.
 *
 * @param {object} controller a Stimulus controller instance
 */
export function useTranslatable(controller) {
    Object.assign(controller, {
        t(key, parameters = {}) {
            const local = resolve(this.translationsValue, key);

            return local === undefined ? translate(key, parameters) : interpolate(local, parameters);
        },
    });
}

/**
 * Walks a nested object segment by segment: `a.b.c` reads `tree.a.b.c`.
 *
 * ⚠️ Segment by segment, so a flat `{'a.b': '…'}` never resolves. That is not an oversight to
 * fix: it is the shape the old Twig partials produce, and pretending otherwise would make the
 * transitional path behave differently from the one it replaces.
 */
function resolve(tree, key) {
    let value = tree;

    for (const segment of key.split('.')) {
        value = value?.[segment];

        if (value === undefined) {
            return undefined;
        }
    }

    return typeof value === 'string' ? value : undefined;
}

/**
 * Literal substitution, matching what `trans()` does with `%count%`-style parameters.
 *
 * ⚠️ No pluralisation here. The transitional tree holds one string per key — it never carried
 * plural forms in the first place — and quietly half-implementing them would hide which labels
 * still need moving to the catalogue.
 */
function interpolate(message, parameters) {
    return Object.entries(parameters).reduce(
        (carry, [name, value]) => carry.split(name).join(String(value)),
        message
    );
}
