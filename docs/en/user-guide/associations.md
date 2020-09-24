# Associations

**Association** – a type of the relationship between products, where one in some way is dependent on the other one(s), or can influence the other one(s) in accordance with different marketing strategies (e.g cross-sell, up-sell, etc.). Each product can associate different products and can be associated from different products.

Associations can be activated and deactivated. Deactivated associations cannot be used in the system, i.e. all records of the associated products will not be transferred via any channel.

## Association Fields

The association entity comes with the following preconfigured fields; mandatory are marked with *:

| **Field Name**           | **Description**                   |
|--------------------------|-----------------------------------|
| Active                   | Activity state of the record      |
| Name (multi-lang) *      | Association name, e.g. сross-sell |
| Backward association     | Backward association name         |
| Description (multi-lang) | The aim of this association usage |

If you want to make changes to the association entity (e.g. add new fields, or modify association views), please contact your administrator.

## Creating

To create a new association record, click `Associations` in the navigation menu to get to the association records [list view](#listing) and then click the `Create Association` button. The common creation window will open:

![Associations creation](../../_assets/associations/associations-create.jpg)

Here enter the association name and description (optional); activate the new association, if needed. Define its backward association using the corresponding select button. Click the `Save` button to finish the association record creation or `Cancel` to abort the process.

Alternatively, use the [quick create](./user-interface.md#quick-create) button on any TreoPIM page and fill in the required fields in the association creation pop-up that appears:

![Creation pop-up](../../_assets/associations/creation-popup.jpg)

For more details on backward associations, please, refer to the [**Associated Products**](./associated-products.md) article in this user guide.

## Listing

To open the list of association records available in the system, click the `Associations` option in the navigation menu:

![Associations list view page](../../_assets/associations/associations-list-view.jpg)

By default, the following fields are displayed on the [list view](./views-and-panels.md#list-view) page for association records:
 - Name
 - Backward association
 - Active

To change the association records order in the list, click any sortable column title; this will sort the column either ascending or descending. 

Association records can be searched and filtered according to your needs. For details on the search and filtering options, refer to the [**Search and Filtering**](./search-and-filtering.md) article in this user guide.

To view some association record details, click the name field value of the corresponding record in the list of associations; the [detail view](./views-and-panels.md#detail-view) page will open showing the association records and the records of the related entities. Alternatively, use the `View` option from the single record actions menu to open the [quick detail](./views-and-panels.md#quick-detail-view-small-detail-view) pop-up.

### Mass Actions

The following mass actions are available for association records on the list view page:
- Remove
- Mass update
- Export
- Add relation
- Remove relation

![Associations mass actions](../../_assets/associations/associations-mass-actions.jpg)

For details on these actions, please, see the [**Mass Actions**](./views-and-panels.md#mass-actions) section of the **Views and Panels** article in this user guide.

### Single Record Actions

The following single record actions are available for association records on the list view page:
- View
- Edit
- Remove

![Associations single record actions](../../_assets/associations/associations-single-actions.jpg)

For details on these actions, please, refer to the [**Single Record Actions**](./views-and-panels.md#single-record-actions) section of the **Views and Panels** article in this user guide.

## Editing

To edit the association, click the `Edit` button on the detail view page of the currently open association record; the following editing window will open:

![Associations editing](../../_assets/associations/associations-edit.jpg)

Here edit the desired fields and click the `Save` button to apply your changes.

Besides, you can make changes in the association record via [in-line editing](./views-and-panels.md#in-line-editing) on its detail view page.

Alternatively, make changes to the desired association record in the [quick edit](./views-and-panels.md#quick-edit-view) pop-up that appears when you select the `Edit` option from the single record actions menu on the associations list view page:

![Edit option](../../_assets/associations/association-editing-popup.jpg)

## Removing

To remove the association record, use the `Remove` option from the actions menu on its detail view page

![Remove1](../../_assets/associations/remove-details.jpg)

or from the single record actions menu on the associations list view page:

![Remove2](../../_assets/associations/remove-list.jpg)

By default, it is not possible to remove the association, if there is any product associated with it.

## Duplicating

Use the `Duplicate` option from the actions drop-down menu to go to the association creation page and get all the values of the last chosen association record copied in the empty fields of the new association record to be created. Modifying the association code is required, as this value has to be unique.

## Working With Associated Products

[Associated products](./associated-products.md) are displayed on the `ASSOCIATED PRODUCTS` panel within the [product](./products.md) detail view page and include the following table columns:

 - Related product image
 - Related product
 - Association

![Associated products panel](../../_assets/associations/associated-products-panel.jpg)

TreoPIM also offers you the ability to add associations to all the products (or as many as needed) simultaneously via the [mass actions](./views-and-panels.md#mass-actions) menu.








