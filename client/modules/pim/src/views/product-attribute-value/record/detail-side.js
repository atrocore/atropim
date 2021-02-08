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
 */

Espo.define('pim:views/product-attribute-value/record/detail-side', 'pim:views/record/detail-side',
    Dep => Dep.extend({
        ownershipOptions: {
            'assignedUser': {
                'config': 'assignedUserAttributeOwnership',
                'field': 'isInheritAssignedUser'
            },
            'ownerUser': {
                'config': 'ownerUserAttributeOwnership',
                'field': 'isInheritOwnerUser'
            },
            'teams': {
                'config': 'teamsAttributeOwnership',
                'field': 'isInheritTeams'
            }
        },

        getInheritedFieldName(field) {
            if (this.getConfig().get(this.ownershipOptions[field].config) && this.model.get('id')) {
                const parts = this.model.get('id').split('~');

                if (parts.length === 2) {
                    let lang = parts.pop();

                    return lang.split('_').reduce((prev, curr) => prev + Espo.Utils.upperCaseFirst(curr.toLocaleLowerCase()), this.ownershipOptions[field].field);
                }
            }

            return Dep.prototype.getInheritedFieldName.call(this, field);
        }
    })
);
