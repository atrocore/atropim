# What is AtroPIM

AtroPIM is a single page application (SPA) with an API-centric architecture and flexible data model based on entities, entity attributes and relations of all kinds among them. AtroPIM allows you to gather and store all your product content in one place, enrich it and spread it to several channels like own online shop, Amazon, eBay, online shops of your distributors, on a tablet or mobile application. AtroPIM will help your to structure and organize all you flexible data and get rid of the Excel mess.

## Main Definitions

[**Locale**](https://atropim.com/store/multi-languages) – a combination of a language (i.e. German, English) and a country/region (i.e. Germany, United Kingdom, the U.S.A.) preferred to be used in the interface. For instance, German German is marked as "de_DE", Austrian German is marked as "de_AT", UK English is marked as "en_GB" and so on. You can use one or more locales in the system. 

This functionality is enabled when the **"Multi-languages"** module is installed to your system. Please, see the details in the [AtroPIM store](https://atropim.com/store/multi-languages).

[**Attribute**](./attributes.md) – a product's property. Each product can be characterized by one or several attributes. There are over 20 attribute types available for you in AtroPIM. Some attribute types allow you to store unique attribute values per locale for your products. Products can have specific attribute values for channels.

[**Attribute group**](./attribute-groups.md) – a way to categorize the attributes. Different attributes of the same nature can be assigned to the same attribute group. You can have multiple attribute groups in AtroPIM.

[**Brand**](./brands.md) – a brand of the product or the name of its manufacturer. Brands create additional possibility to categorize the products.

[**Category**](./categories.md) – a way to classify and group the products by certain criteria. A category can have only one parent category. A category without a parent category is called a root category. A root category starts a category tree. In AtroPIM you can have multiple category trees. A category tree can have unlimited levels. A category without any child category is called a leaf category.

[**Channel**](./channels.md) – a destination point for your product information, for example, your online shop, mobile app or print catalog. A channel defines a set of product information, which can be synced with the third-party systems as well as exported in certain formats.

[**Product Family**](./product-families.md) – a means of standardizing your product information that helps you group similar products, which use similar or same production processes, have similar physical characteristics, and may share customer segments, distribution channels, pricing methods, promotional campaigns, and other elements of the marketing mix. In AtroPIM product families are use to define a set of attributes that are shared by products belonging to a certain family, to describe the characteristics of these products. A product can belong to only one product family.

[**Product**](./products.md) – an item in physical, virtual or cyber form as well as a service offered for sale. Each product is made at a cost and is sold at a price. Each product has a certain type (e.g. simple, configurable, etc.) and can be assigned to a certain product family, which will define, what attributes are to be set for this product. Product can be assigned to several categories, be of a certain brand, described in several languages and be prepared for selling via different channels. A product can be in association of a certain type with some other products.

**Product Type** – a kind of product definition that specifies in which way the product should be described. Products of different types may have different or additional UIs, to define all specific product options needed.

[**Association**](./associations.md) – a means of regulating types of relationships between the products. In AtroPIM associations can be one-sided, when Product A is associated with Product B and not vice versa, or two-sided, when both Product A and Product B are associated with each other.

[**Module**](https://atropim.com/store) – an extension or module of the AtroPIM system aimed at expanding or modifying its functionality to such an extend that all customer needs are met. AtroPIM is an extremely flexible system, so it is possible to change almost everything. "Connector" is a module, which is implemented to interact with the third-party systems and exchange the data between them. The API of AtroPIM or API of a third-party system can be used for it.

[**Dashboard**](./dashboards-and-dashlets.md) – a collection of data displayed in a graphical or table layout as widgets. Dashboards allow users to have all the important information grouped by a certain type or nature in one single place. Every user can have several dashboards.

