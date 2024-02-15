/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-attribute-value/fields/attribute', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['notLinkedWithProductAttributeValue', 'fromAttributesTab'],

        listTemplate: 'pim:product-attribute-value/fields/attribute',

        boolFilterData: {
            notLinkedWithProductAttributeValue() {
                return {
                    productId: this.model.get('productId'),
                    channelId: this.model.get('channelId')
                };
            },
            fromAttributesTab() {
                if (!this.model.get('productId')) {
                    return;
                }
                return {
                    tabId: this.model.tabId ? this.model.tabId : null
                };
            }
        },

        createDisabled: true,

        setup() {
            this.mandatorySelectAttributeList = ['type', 'isMultilang', 'defaultScope', 'defaultChannelId', 'defaultChannelName', 'isRequired'];
            if (this.model.get('attributeTooltip')) {
                var $a;
                this.once('after:render', function () {
                    $a = $('<a href="javascript:" class="text-muted field-info"><span class="fas fa-info-circle"></span></a>');
                    var $label = this.$el.find('a[data-tooltip="'+this.model.get('attributeId')+'"]');
                    $label.append(' ');
                    $label.append($a);
                    $a.popover({
                        placement: 'bottom',
                        container: 'body',
                        html: true,
                        content: this.model.get('attributeTooltip').replace(/\n/g, "<br />"),
                        trigger: 'click',
                    }).on('shown.bs.popover', function () {
                        $('body').one('click', function () {
                            $a.popover('hide');
                        });
                    });
                }, this);
                this.on('remove', function () {
                    if ($a) {
                        $a.popover('destroy')
                    }
                }, this);
            }


            Dep.prototype.setup.call(this);
        },

        select(model) {
            this.setAttributeFieldsToModel(model);

            Dep.prototype.select.call(this, model);
            this.model.trigger('change:attribute', model);
        },

        setAttributeFieldsToModel(model) {
            let attributes = {
                attributeType: model.get('type'),
                attributeExtensibleEnumId: model.get('extensibleEnumId'),
                attributeMeasureId: model.get('measureId'),
                attributeIsDropdown: model.get('dropdown'),
                amountOfDigitsAfterComma: model.get('amountOfDigitsAfterComma'),
                attributeIsMultilang: model.get('isMultilang'),
                defaultScope: model.get('defaultScope'),
                defaultChannelId: model.get('defaultChannelId'),
                defaultChannelName: model.get('defaultChannelName'),
                isRequired: model.get('isRequired')
            };
            this.model.set(attributes);
        },

        data() {
            let data = Dep.prototype.data.call(this);
            let attributeData = this.model.get('data') || {};

            if ('title' in attributeData) {
                data.titleValue = attributeData.title;
            } else {
                data.titleValue = data.nameValue;
            }

            return data;
        },

        clearLink() {
            this.unsetAttributeFieldsInModel();

            Dep.prototype.clearLink.call(this);
        },

        unsetAttributeFieldsInModel() {
            ['attributeType', 'attributeIsMultilang'].forEach(field => this.model.unset(field));
        }

    })
);

