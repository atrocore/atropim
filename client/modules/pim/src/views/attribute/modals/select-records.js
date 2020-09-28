

Espo.define('pim:views/attribute/modals/select-records', 'views/modals/select-records',
    Dep => Dep.extend({

        mandatorySelectAttributeList: ['typeValue'],

        loadList() {
            let inputLanguageList = this.getConfig().get('inputLanguageList') || [];
            if (this.getConfig().get('isMultilangActive') && inputLanguageList.length) {
                let typeValueFields = inputLanguageList.map(lang => {
                    return lang.split('_').reduce((prev, curr) => prev + Espo.Utils.upperCaseFirst(curr.toLocaleLowerCase()), 'typeValue');
                });
                this.mandatorySelectAttributeList = this.mandatorySelectAttributeList.concat(typeValueFields);
            }

            Dep.prototype.loadList.call(this);
        }

    })
);