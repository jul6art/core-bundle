// Fixture read by JsTranslationGuardTest through AbstractJsTranslationTestCase.
// Both keys exist in Tests/Fixtures/translations/javascript.{en,fr}.yaml — that is the point.
export default class {
    connect() {
        this.element.textContent = this.t('datatable.filters');
    }

    onError() {
        this.notify(this.t('datatable.error.saving'));
    }
}
