# Connectivity, data import and export



## Which systems can TreoPIM be integrated with?

TreoPIM can be integrated with any third-party system that enables integration, it can be online shops, ERP, CRM, PLM, MDM, CMS and other systems. TreoPIM is a web-based software with a REST API that can be used for integration purposes. Connectors for TreoPIM can also be programmed, which can use the APIs of third-party systems.

If integration via API from TreoPIM or a third-party system is technically not possible, data exchange can still be ensured by exchanging files.

  

## How can TreoPIM be integrated with other systems?

There are absolutely no restrictions on integration with other systems. The data exchange can be organized as follows:

- Via TreoPIM REST API
- Via API from a third-party system, for which a connector for TreoPIM must be created that will address this API.
- Via manual export and import of entries (available for all entities in the system)
- Via automatic export and import of entries - Via import feeds and export feeds (import feeds and export feeds modules required).




## Does TreoPIM have an API?

Yes, as an application with a service-oriented software architecture, TreoPIM has a full REST API. This is also immediately available for custom entities and fields after they are set up.

  

## Are there any restrictions on data exchange with third-party systems?

No, TreoPIM has absolutely no restrictions on data exchange with third-party systems.

  

## Can I export data from TreoPIM?

Each user can export the entries for which he is authorized, the format (CSV or XLSX) or the data volume is determined (which fields are to be exported - all or only the selected ones). The entries are exported per entity - that is, when you are with the products, the product entries are exported without data from the dependent entities such as attributes, categories, associations etc.

To implement more complicated export scenarios, e.g. If the entire product catalog, including all associated information, is to be exported at the same time, we recommend using our Export Feeds module.



## Can I import data into TreoPIM?

Thanks to the import configurator, the administrator can import a CSV file with data into any entity in the system. The locale settings and field mapping must be carried out; the standard values for columns can also be specified.

The import can take place per entity - i.e. when you are with the products, the product entries are imported without data for dependent entities such as attributes, categories, associations etc.

To implement more complicated import scenarios, e.g. if If the entire product catalogs including all related information are to be imported at the same time, we recommend using our import feeds module.

  

## Is it possible to exchange data with TreoPIM fully automatically?

Yes, the fully automatic data exchange with the TreoPIM system is possible. This can e.g. secured either via REST API from TreoPIM, via an API from a third-party system or via a file exchange using an FTP server.

  

## Who is authorized to export data from TreoPIM?

Every user is authorized to export all entries to which he has access. It is possible to prohibit the export function at the program level. Contact TreoLabs GmbH or your TreoPIM developer for this.



## Who is authorized to import data into TreoPIM?

By default, only the administrator is authorized to import data into TreoPIM. Thanks to the Import Feeds module, advanced import scenarios can be implemented, even by other users in the system who are authorized to do so.
