/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/detail', ['pim:views/detail', 'pim:views/product/fields/classifications-single'],
    (Dep, ClassificationSingle) => Dep.extend({

        selectRelatedFilters: {
            classifications: function () {
                return ClassificationSingle.prototype.getSelectFilters.call(this)
            }
        },

        selectBoolFilterLists: {
            attributes: ['notLinkedWithProduct'],
        },

        boolFilterData: {
            attributes: {
                notLinkedWithProduct() {
                    return this.model.id;
                },
            },
        },

        events: _.extend({
            'click a[data-action="setPavAsInherited"]': function (e) {
                let $a = $(e.currentTarget);
                this.ajaxPostRequest(`ProductAttributeValue/action/inheritPav`, {id: $a.data('pavid')}).then(response => {
                    this.notify('Saved', 'success');
                    this.model.trigger('after:attributesSave');
                    $a.parents('.panel').find('.action[data-action=refresh]').click();
                });
            },
        }, Dep.prototype.events),

        actionNavigateToRoot(data, e) {
            e.stopPropagation();

            this.getRouter().checkConfirmLeaveOut(function () {
                const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
                if (rootUrl !== `#${this.scope}`) {
                    this.getRouter().navigate(rootUrl, {trigger: true});
                } else {
                    const options = {
                        isReturn: true
                    };
                    this.getRouter().navigate(rootUrl, {trigger: false});
                    this.getRouter().dispatch(this.scope, null, options);
                }
            }, this);
        }

    })
);

