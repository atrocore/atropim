/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/classification-attribute/fields/attribute', 'views/fields/link',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:attributeId', () => {
                this.setAttributeData();
            });
        },

        select: function (model) {
            Dep.prototype.select.call(this, model);

            this.model.trigger('change:attribute', model);
            this.model.set('isRequired', model.get('isRequired'))
        },

        setAttributeData() {
            this.model.set('attributeType', null);

            if (this.model.get('attributeId')) {
                this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).success(attribute => {
                    this.model.set('attributeType', attribute.type);
                    this.model.set('attributeMeasureId', attribute.measureId);
                    this.model.set('maxLength', attribute.maxLength);
                    this.model.set('countBytesInsteadOfCharacters', attribute.countBytesInsteadOfCharacters);
                    this.model.set('min', attribute.min);
                    this.model.set('max', attribute.max);
                });
            }
        },

    })
);

