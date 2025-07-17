/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/listing/fields/classification', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['onlyForEntity', 'onlyForChannel'],

        boolFilterData: {
            onlyForEntity() {
                return 'Listing';
            },
            onlyForChannel() {
                return this.model.get('channelId');
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:channelId', () => {
                if (this.model.isNew()) {
                    this.model.set('classificationId', null);
                    this.model.set('classificationName', null);
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get('channelId')) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        },

    })
);
