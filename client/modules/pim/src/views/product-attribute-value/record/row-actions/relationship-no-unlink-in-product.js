/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-attribute-value/record/row-actions/relationship-no-unlink-in-product', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        pipelines: {
            actionListPipe: ['clientDefs', 'ProductAttributeValue', 'actionListPipe']
        },

        setup() {
            Dep.prototype.setup.call(this);
            this.once('after:render', () => {
                this.setupIcons()
            });
        },

        setupIcons() {
            let iconContainer = $("<div class='icons-container'> </div>")
            this.$el.find('.list-row-buttons').prepend(iconContainer)
            this.createView('icons', 'pim:views/product-attribute-value/fields/icons', {
                el: this.options.el + '.list-row-buttons .icons-container',
                scope: 'ProductAttributeValue',
                model: this.model,
                mode: 'list'
            }, (view) => view.render())
        },

        getActionList() {
            let list = [{
                action: 'quickView',
                label: 'View',
                data: {
                    id: this.model.id
                },
                link: '#' + this.model.name + '/view/' + this.model.id
            }];
            if (this.getAcl().checkModel(this.model ,'edit')) {
                list = list.concat([
                    {
                        action: 'quickEdit',
                        label: 'Edit',
                        data: {
                            id: this.model.id
                        },
                        link: '#' + this.model.name + '/edit/' + this.model.id
                    }
                ]);
            }

            if (this.getAcl().checkModel(this.model ,'delete')) {
                list.push({
                    action: 'removeRelated',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });

                if (
                    this.getMetadata().get('scopes.Product.relationInheritance') === true
                    && !(this.getMetadata().get('scopes.Product.unInheritedRelations') || []).includes('productAttributeValues')
                ) {
                    list.push({
                        action: 'removeRelatedHierarchically',
                        label: 'removeHierarchically',
                        data: {
                            id: this.model.id
                        }
                    });
                }
            }
            this.runPipeline('actionListPipe', list);
            return list;
        }
    })
);
