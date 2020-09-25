# Administration

  

## Is it possible to adapt user interfaces from the admin area?

Yes, it is possible to adjust the layout of list and detail pages directly from the admin area using the Layout Manager. You can use drag-and-drop not only to determine the fields to be displayed, but also their order and assignment to a group of elements.



## Can you create custom fields?

Yes, you can create custom fields for each entity in the system (i.e. products, attributes, categories, associations, product families, etc.). Use the Entity Manager for this.

TreoPIM offers the user much more options than just creating user-defined fields, because TreoPIM has a completely flexible data model. You can create new entities, edit existing entities, create and change the relationships between the entities and edit the metadata.



## Can the user restrict the authorizations?

Yes, TreoPIM has a very flexible access and authorization concept. The roles determine which user can do what and with which entities. It is also possible to determine the access level of the users only for own entries, entries of the own team or all entries.

  

## Can you edit the permissions at field level?

Yes, for each role and each user it is possible to set up the authorizations at field level, so that e.g. a user can see entries from an entity, but without values for a specific field, e.g. Price, internal notes etc.

When setting up the authorizations at field level, it is possible to determine whether a user can see the field or not or whether he can edit the value.



## What is ACL Strict Mode?

ACL Strict Mode determines the behavior of the system when access is granted.

If ACL Strict Mode is deactivated, you automatically have access to all entities, including those that are not actually configured for the user. If ACL Strict Mode is activated, you automatically only have access to the allowed entities.

We recommend activating the ACL Strict Mode from the start.



## What is conditional field logic?

It is possible to set the conditions, whether a field should be visible, read-only or a mandatory field. As a condition e.g. the value of another field can be used, e.g. if the status of an entry is “Approved”, the “Name” field can no longer be edited.

  

## Can TreoPIM be updated from the admin area?

It is possible to update the TreoPIM directly from the admin area. However, we recommend that you only have this done by your TreoPIM developers. The admin will receive a notification when an update is available.



## Can I update TreoPIM modules from the admin area?

Yes, thanks to the Module Manager it is possible to install, update, activate or deactivate and uninstall both the individual and the official TreoPIM modules directly from the admin area.

However, we recommend that updates only be carried out by your TreoPIM developers.

  

## Can I have tasks and tasks run in the background according to a schedule?

Yes, there are “Scheduled Jobs” for this in TreoPIM. It is possible to configure which scripts are to be executed on which schedule. Both the system scripts and the individual scripts can be executed as “Scheduled Jobs”.

  

## Can you change the theme?

There is a predefined theme in TreoPIM - Treo Dark Theme. You can also have your own themes created, e.g. to adapt a color scheme to the company colors.

  

## Can the navigation bar be placed flexibly?

Yes, the navigation bar can be placed on the left, top or right. The place is determined in the theme. In the standard TreoPIM view, the navigation bar is placed on the left.

## Can you configure the navigation bar?

Yes, you can configure the order of the elements and the icons for elements. If you use our 2-level navigation module, you can also define groups of elements and arrange these groups and the elements within a group.

  

## Can you change the number of entries on list views?

Yes, you can set how many entries are to be displayed in all list views.

  

## Can the dashboard be preset?

Yes, the administrator can configure the standard dashboards using drag and drop, more than one dashboard can be configured.

  

## Is there an action log?

Yes, if set, the administrator can trace all actions of all users in the system very precisely. It is documented who has changed which entity, which values (fields) and which entries. The read access is also documented. The entries in the action log can be searched.

  

## Are the entries really deleted? Can you recover deleted data?

In order not to endanger the consistency of the system, no entries are deleted in TreoPIM. These only get the property “isDeleted” so that the system knows that this entry can no longer be displayed to the users.

Thus it is possible to restore the "deleted" data. This should best be done by TreoLabs GmbH or your TreoPIM developers.
