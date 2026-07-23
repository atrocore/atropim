[![GitHub Stars](https://img.shields.io/github/stars/atrocore/atropim?style=flat&logo=github&color=yellow)](https://github.com/atrocore/atropim/stargazers)
[![GitHub Forks](https://img.shields.io/github/forks/atrocore/atropim?style=flat&logo=github&color=orange)](https://github.com/atrocore/atropim/network/members)
[![GitHub last commit](https://img.shields.io/github/last-commit/atrocore/atropim)](https://github.com/atrocore/atropim/commits/master)
[![License](https://img.shields.io/github/license/atrocore/atropim)](https://github.com/atrocore/atropim/blob/master/LICENSE.txt)
[![Built with PHP](https://img.shields.io/badge/PHP-8.1%2B-blue?logo=php)](https://www.php.net/)
[![Documentation](https://img.shields.io/badge/Docs-Help%20Center-blueviolet)](https://help.atrocore.com/release-notes/pim)

<p align="center" width="100%">
  <br><br>
<img src="_assets/atropim-logo.svg" alt="AtroPIM Logo" height="48">
  <br><br>
</p>

AtroPIM is a highly-configurable, modular, API-first open-source [Product Information Management (PIM) system](https://www.atropim.com). A lightweight alternative to Akeneo & Pimcore built on PHP & Svelte, ideal for eCommerce, ERP integrations, and B2B catalogs.

It enables manufacturers, brands, and retailers to efficiently centralize, manage, and distribute product data across multiple channels – making it ideal for scalable eCommerce and digital product management.
<!--
| Host            | URL                                          |
| --------------- | -------------------------------------------- |
| Main Repository | https://gitlab.atrocore.com/atrocore/atropim |
| Mirror (GitHub) | https://github.com/atrocore/atropim          |
-->

This repository contains source code for a PIM module for the AtroCore Data Platform. AtroPIM is technically an instance of [AtroCore](https://github.com/atrocore/atrocore) which has a PIM module installed on it.


## Evolution

Our software has been in active development since 2018. It all began with a simple idea: to create a better open-source PIM solution for our customers.

Today, our software has evolved into a robust, comprehensive ecosystem built on a highly flexible, modular architecture. This adaptable framework allows us to confidently assure our clients that their requirements – extending far beyond standard PIM functions – can be fully accommodated without compromise. By offering a versatile technical toolbox, we enable organizations to seamlessly scale, integrate, and customize their data models to meet complex, ever-changing business demands.

## Our Customers

Our customers are manufacturers, wholesalers, and distributors that manage complex and business critical data across products, assets, and processes. They rely on AtroCore to handle large and sophisticated data models, extensive product portfolios, and complex variant, classification, and integration requirements.

From mid market companies to global enterprises, organizations choose AtroCore when their needs go beyond the limitations of standard software solutions and when flexibility, scalability, and seamless integration with existing software landscapes are essential.

We are proud to partner with leading global brands and enterprise market leaders, including: Acer, Bridgestone and Adam Hall.


## Why Choose AtroPIM?

AtroPIM is a flexible, open-source Product Information Management platform designed for companies that need to manage complex product data, integrate multiple systems, and adapt their PIM solution to specific business requirements.

Unlike traditional PIM systems with rigid data models and costly customization projects, AtroPIM provides a highly configurable architecture that allows businesses to create, manage, and distribute product information exactly the way they need it.

* **API-First & Headless Architecture:** Automatically provides a comprehensive REST API for all standard and custom data models, enabling seamless integration with ecommerce platforms, ERP systems, marketplaces, and other applications.
* **Highly Configurable Data Model:** Create custom entities, attributes, relations, classifications, layouts, and workflows directly through the user interface without modifying the core system.
* **Open-Source & Extensible:** Built on a GPLv3 licensed open-source foundation with a modular architecture that allows developers to extend functionality and create custom solutions.
* **Flexible for Complex Product Data:** Manage sophisticated product structures, variants, technical specifications, classifications, relationships, and any other data requirements beyond standard catalog scenarios.
* **Modern & Efficient Technology Stack:** Benefit from a lightweight, high-performance architecture designed for efficient operation and scalable deployments.
* **Responsive User Experience:** Provide employees, partners, and other stakeholders with a modern, mobile-friendly interface.
* **Scalable Architecture:** Grow from initial implementations to enterprise environments with increasing data volumes, users, integrations, and distribution channels.

## Use AtroPIM If You:

* **Manage complex or non-standard product data:** Build flexible product models with custom entities, attributes, relationships, and multi-level structures without lengthy development projects.
* **Need a PIM that adapts to your business instead of forcing your business into predefined structures:** Configure AtroPIM around your processes, products, and data requirements.
* **Want a headless PIM for modern digital commerce:** Use the built-in REST API to connect AtroPIM with ecommerce platforms, mobile applications, portals, marketplaces, and any other digital channels.
* **Require deep integration with ERP and enterprise systems:** Synchronize product information with SAP, Microsoft Dynamics, Odoo, and other business applications using APIs, connectors, and flexible import/export capabilities.
* **Operate internationally across multiple markets and channels:** Manage multilingual content, regional requirements, channel-specific data, and different product presentations from a central source of truth.
* **Need full control over your data and infrastructure:** Use a self-hosted, GPLv3 open-source platform with no vendor lock-in and complete ownership of your data.
* **Look for a cost-efficient enterprise-grade PIM solution:** Reduce implementation and maintenance costs through configuration-first customization, open architecture, and reduced dependency on proprietary development.

## How does AtroPIM differ from AtroCore?

**AtroCore** is the open-source framework and core ecosystem. It provides the foundational architecture, user management, API, data model configuration, and low-code capabilities. By itself, it is an abstract entity-relationship system used to build custom business software.

**AtroPIM** consists of the underlying AtroCore platform with a specialized PIM module pre-installed on top of it. This combination provides a complete Product Information Management system, adding all the specific features required to manage complex product data, catalogs, and channels.


## Features

AtroPIM comes with a lot of features:

- All [AtroCore's features](https://github.com/atrocore/atrocore/blob/master/README.md#feature-list) plus:
- Product-Centric Data Management
- Hierarchical product variants
- Channels and channel-spesific attributes
- Product category trees
- Product classifications
- Custom text blocks
- Bidirectional product associations (upsell, cross-sell, etc.)
- Product Listings
- PIM-Focused Dashboard Widgets

Visit [this page](https://www.atropim.com/en/features) to see all the features of AtroPIM.

### Product Development Roadmap

Check out our [roadmap](https://community.atrocore.com/t/product-roadmap/237).


### Free vs Paid

Every business, from small startups to large enterprises, use the exact same powerful, open-source core: AtroCore. Because our free core modules – including AtroPIM, Import, and Export – are incredibly feature-rich, the **free version is more than enough to satisfy the needs of the vast majority of users**.

You only need to expand your system with paid Premium Modules if your business scales to require highly specialized, enterprise-grade capabilities.

For teams that prefer a managed cloud environment, we offer hosted SaaS plans.


## Technology

![Architecture and Technologies](_assets/architecture-and-technologies.png)

- Backend: PHP, powered by enterprise-grade Symfony and Laminas components.
- Frontend: JavaScript, migrating from legacy Backbone.js to a modern, reactive Svelte architecture.
- Database: PostgreSQL, MySQL, and MariaDB, managed via the Doctrine DBAL abstraction layer.
- API: Fully standardized using OpenAPI (Swagger) specifications.
- Update Management: Driven by Composer for seamless dependency and version handling.

## Integrations

AtroPIM has a REST API and can be integrated with any third-party system, channel or marketplace. 

We offer the following native paid integrations:

- **Multichannel tools**: Channable, ChannelPilot, Lengow, Feedonomics, Productsup, Channelengine, ChannelAdvisor, and others
- **ERPs**: SAP S/4 HANA, Odoo, SAP Business One, Oracle Fusion, Business Central, Acumatica, Infor, Oracle Netsuite, Xentral, Infor, Epicor. Work4all, and others
- **E-Commerce Platforms**: Adobe Commerce (Magento 2), Bigcommerce, Saleor, Commercetools, Sap Commerce Cloud, Salesforce Commerce Cloud, Shopware, Prestashop, WooCommerce, Shopify, Sylius, Vendure,  and others
- **Marketplaces**: Amazon, Otto.

Read [this article](https://store.atrocore.com/en/atrocore-integrations-for-erp-ecommerce-marketplaces) to better understand how our integrations work.

You can **build your own fully automated integration** with any third-party system via its REST / GraphQL API using our free modules: 
- Import: HTTP Requests and/or 
- Export: HTTP Requests.

Please [contact us](https://www.atropim.com/contact), if you want to know more.


## System Requirements

- Linux-based **root or managed server** (recommended: Ubuntu LTS). 
- **Minimum Ressources:**
  - 2 vCPU
  - 4 GB RAM
  - 80 GB SSD Storage
- **Software**:
  - Apache Web Server or Nginx
  - PHP 8.1 - 8.4.
  - PostgreSQL 14.9+ (recommended) or MySQL 5.5+ or MariaDB 5.5+.

> Please note that AtroCore/AtroPIM will not run on standard shared hosting environments due to its technical requirements and resource needs. Managed server hosting can be suitable, but each provider and configuration should be evaluated individually. In most cases it will work.


## Installation (Getting Started)

To install AtroPIM you need to install Atrocore and a PIM module for it.

Installation Guide is [here](https://help.atrocore.com/installation-and-maintenance/installation).

### Docker Installation

Installation Guide for Docker is [here](https://help.atrocore.com/installation-and-maintenance/installation/docker-configuration).

Docker Image is [here](https://github.com/atrocore/docker).

> We recommend to use Docker Image to play with the system, and standard installation for production environment.

## Screenshots
|                                                                                          |                                                                                          |
| ---------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| [![Dashboard](_assets/dashboard.png)](_assets/dashboard.png)                             | [![Files](_assets/files.png)](_assets/files.png)                                         |
| [![Product List](_assets/product-list.png)](_assets/product-list.png)                    | [![Product Cards](_assets/product-cards.png)](_assets/product-cards.png)                 |
| [![Product Details 1](_assets/product-details1.png)](_assets/product-details1.png)       | [![Product Details 2](_assets/product-details2.png)](_assets/product-details2.png)       |
| [![Layout Management 1](_assets/layout-management1.png)](_assets/layout-management1.png) | [![Layout Management 2](_assets/layout-management2.png)](_assets/layout-management2.png) |

## Public Demo Instance

- URL: https://demo.atropim.com/
- Login: admin
- Password: admin
     

## Contributing

- **Report bugs:** please [report bugs](https://github.com/atrocore/atrocore/issues/new).
- **Fix bugs:** please create a pull request in the affected repository including a step by step description to reproduce the problem.
- **Contribute features:** You are encouraged to create new features. Please contact us before you start.


## Localization

Would you like to help us translate UIs into your language, or improve existing translations?
- https://translate.atrocore.com/


## Documentation
- Please visit our Help Center (Documentation) - https://help.atrocore.com/


## Other Resources

- Report a Bug - https://github.com/atrocore/atrocore/issues/new
- Read our Release Notes - https://help.atrocore.com/release-notes/pim
- Please visit our Community - https://community.atrocore.com
- Сontact us - https://www.atrocore.com/contact


## 📌Help Us Grow

If you find AtroCore useful:

- ⭐ Star the repo
- 🗣️ Share it with your network
- 🛠️ Contribute to the project

## License

AtroPIM is published under the GNU GPLv3 [license](LICENSE.txt).
