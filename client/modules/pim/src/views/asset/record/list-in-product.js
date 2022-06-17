/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('pim:views/asset/record/list-in-product', 'views/record/list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.options.panelView) {
                this.listenTo(this.options.panelView.model, 'overview-filters-changed', () => {
                    this.applyOverviewFilters();
                });
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.applyOverviewFilters();
        },

        applyOverviewFilters() {
            if (!this.collection || this.collection.total === 0) {
                return
            }

            const scopeFilter = this.getStorage().get('scopeFilter', 'OverviewFilter');

            this.collection.models.forEach(model => {
                let channelId = model.get('channel') || 'Global';

                let hide = false;
                if (!scopeFilter.includes('allChannels')) {
                    // hide channel
                    if (!hide && !scopeFilter.includes(channelId)) {
                        hide = true;
                    }
                }

                this.controlFieldVisibility(this.getView(model.get('id')), hide);
            });
        },

        controlFieldVisibility(view, hide) {
            if (hide) {
                view.$el.hide();
                view.overviewFiltersHidden = true;
            } else if (view.overviewFiltersHidden) {
                view.$el.show();
            }
        },

    })
);