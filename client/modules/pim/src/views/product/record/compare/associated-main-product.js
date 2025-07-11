/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/compare/associated-main-product', 'views/record/compare/relationship', function (Dep) {
    return Dep.extend({
        setup() {
            this.selectFields = ['id', 'name', 'mainImageId', 'mainImageName'];
            Dep.prototype.setup.call(this);
        },

        getFieldColumns(linkEntity) {
            let data = Dep.prototype.getFieldColumns.call(this, linkEntity);
            let key = linkEntity.id + 'MainImage';
            data.push({
                label: '',
                isField: true,
                key: key,
                entityValueKeys: []
            });

            this.getModelFactory().create('Product', model => {
                model.set(linkEntity);
                let viewName = model.getFieldParam('mainImage', 'view') || this.getFieldManager().getViewName(model.getFieldType('mainImage'));
                this.createView(key, viewName, {
                    el: `${this.options.el} [data-key="${key}"] .attachment-preview`,
                    model: model,
                    readOnly: true,
                    defs: {
                        name: 'mainImage',
                    },
                    mode: 'detail',
                    inlineEditDisabled: true,
                }, view => {
                    view.previewSize = 'small';
                })
            });

            return data;
        },
    });
});