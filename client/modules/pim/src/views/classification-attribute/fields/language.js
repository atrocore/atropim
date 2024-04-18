/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/classification-attribute/fields/language', 'views/fields/language', Dep => {
    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:attribute', () => {
                this.reRender();
            });
        },

        setupOptions(){
            this.params.options = ['main']
            this.translatedOptions = {'main': this.translate('mainLanguage', 'labels', 'Global')};

            if (this.getConfig().get('isMultilangActive')) {
                (this.getConfig().get('inputLanguageList') || []).forEach(language => {
                    this.params.options.push(language);
                    this.translatedOptions[language] = language;
                });
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit' || this.mode === 'detail') {
                this.hide();
                if ((!this.model.isNew() || this.model.urlRoot === 'ProductAttributeValue') && this.model.get('attributeId')) {
                    this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).success(attr => {
                        if (attr.isMultilang) {
                            this.show();
                        }
                    });
                }
            }
        }

    });
});