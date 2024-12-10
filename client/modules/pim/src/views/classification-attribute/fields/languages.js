/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/classification-attribute/fields/languages', 'views/fields/multi-language', Dep => {
    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:attribute', () => {
                this.reRender();
            });
        },

        setupOptions() {
            this.params.options = []
            this.translatedOptions = {}
            
            const languages = Object.values(this.getConfig().get('referenceData').Language)
            languages.forEach(language => {
                if (language.role === 'main') {
                    this.params.options.unshift('main')
                    this.translatedOptions['main'] = language.name;
                } else if (language.role === 'additional') {
                    this.params.options.push(language.code)
                    this.translatedOptions[language.code] = language.name;
                }
            })
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit' || this.mode === 'detail') {
                this.hide();
                if (this.model.isNew() && this.model.get('attributeId')) {
                    this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).success(attr => {
                        if (attr.isMultilang) {
                            this.show();
                            this.model.set('languages', ['main']);
                        }
                    });
                }
            }
        }

    });
});