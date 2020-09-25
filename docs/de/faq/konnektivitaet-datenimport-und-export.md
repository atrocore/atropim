# Konnektivität, Datenimport und -Export



## Mit welchen Systemen kann AtroPIM integriert werden?

AtroPIM kann mit beliebigen Drittsystemen integriert werden, die eine Integration ermöglichen, es können Onlineshops, ERP, CRM, WaWis, PLM, MDM, CMS und sonstige Systeme sein. AtroPIM ist eine webbasierte Software mit einer REST API, die für Integrationszwecke genutzt werden kann. Es können auch Connectoren für AtroPIM programmiert werden, die die APIs von Drittsystemen verwenden können.

Falls eine Integration über API von AtroPIM oder einem Drittsystem technisch nicht möglich ist, kann man den Datenaustausch immer noch über den Austausch von Dateien sicherstellen.

  

## Wie kann AtroPIM mit anderen Systemen integriert werden?

Es gibt absolut keine Einschränkungen bei der Integration mit anderen Systemen. Der Datenaustausch kann wie folgt organisiert werden:

- Über AtroPIM REST API
- Über API von einem Drittsystem, dafür ist ein Connector für AtroPIM zu erstellen, der diese API ansprechen wird.
- Über manuellen Export und Import von Einträgen (verfügbar für alle Entitäten im System)
- Über automatischen Export und Import von Einträgen – über Import Feeds und Export Feeds (Import Feeds und Export Feeds-Module notwendig).




## Hat AtroPIM eine API?

Ja, als eine Anwendung mit einer serviceorientierten Software-Architektur hat AtroPIM eine vollwertige REST API. Diese ist auch für benutzerdefinierte Entitäten und Felder nach Einrichtung sofort verfügbar.

  

## Gibt es Einschränkungen beim Datenaustausch mit Drittsystemen?

Nein, seitens AtroPIM gibt es absolut keine Einschränkungen beim Datenaustausch mit Drittsystemen.

  

## Kann man Daten aus AtroPIM exportieren?

Jeder Benutzer kann die Einträge, für die er berechtigt ist, exportieren, dabei wird das Format (CSV oder XLSX) oder der Datenumfang bestimmt (welche Felder zu exportieren sind – alle oder nur die ausgewählten). Die Einträge werden pro Entität exportiert – das heißt, wenn man bei den Produkten ist, werden die Produkteinträge exportiert, ohne Daten aus den abhängigen Entitäten wie Attribute, Kategorien, Assoziationen etc.

Um kompliziertere Export-Szenarien umzusetzen, z.B. wenn die gesamten Produktkataloge inkl. aller dazugehörigen Informationen zugleich exportiert werden sollen, empfehlen wir, unser Export Feeds-Modul zu nutzen.



## Kann man Daten in AtroPIM importieren?

Dank dem Import-Konfigurator kann der Administrator eine CSV-Datei mit Daten in jede Entität des Systems importieren. Dabei sind die Locale-Einstellungen und Feld-Mapping vorzunehmen, es können auch die Standardwerte für Spalten vorgegeben werden.

Der Import kann pro Entität erfolgen -– d.h. wenn man bei den Produkten ist, werden die Produkteinträge importiert, ohne Daten für abhängige Entitäten wie Attribute, Kategorien, Assoziationen etc.

Um kompliziertere Import-Szenarien umzusetzen, wenn z.B. die gesamten Produktkataloge inkl. aller dazugehörigen Informationen zugleich importiert werden sollen, empfehlen wir, unser Import Feeds-Modul zu nutzen.

  

## Ist es möglich, Daten mit AtroPIM vollautomatisch auszutauschen?

Ja, der vollautomatische Datenaustausch mit dem AtroPIM-System ist möglich. Dieser kann z.B. entweder über REST API von AtroPIM, über eine API von einem Drittsystem oder über einen Dateiaustausch mittels FTP-Server gesichert werden.

  

## Wer ist berechtigt, Daten aus AtroPIM zu exportieren?

Jeder Nutzer ist berechtigt, alle Einträge, auf die er Zugriff hat, zu exportieren. Es ist möglich, die Exportfunktion auf Programmebene zu verbieten. Kontaktieren Sie dafür uns oder Ihre AtroPIM-Entwickler.



## Wer ist berechtigt, Daten in AtroPIM zu importieren?

Standardmäßig ist nur der Administrator berechtigt, Daten in AtroPIM zu importieren. Dank dem Import Feeds-Modul können fortgeschrittene Importszenarien umgesetzt werden, auch von anderen Nutzern im System, die dafür berechtigt sind.
